<?php
// Copyright (C) 2015 Combodo SARL
//
//   This file is part of iTop.
//
//   iTop is free software; you can redistribute it and/or modify
//   it under the terms of the GNU Affero General Public License as published by
//   the Free Software Foundation, either version 3 of the License, or
//   (at your option) any later version.
//
//   iTop is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU Affero General Public License for more details.
//
//   You should have received a copy of the GNU Affero General Public License
//   along with iTop. If not, see <http://www.gnu.org/licenses/>

/**
 * Special kind of Graph for producing some nice output
 *
 * @copyright   Copyright (C) 2015 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

class DisplayableNode extends GraphNode
{
	public $x;
	public $y;
	
	/**
	 * Create a new node inside a graph
	 * @param SimpleGraph $oGraph
	 * @param string $sId The unique identifier of this node inside the graph
	 * @param number $x Horizontal position
	 * @param number $y Vertical position
	 */
	public function __construct(SimpleGraph $oGraph, $sId, $x = 0, $y = 0)
	{
		parent::__construct($oGraph, $sId);
		$this->x = $x;
		$this->y = $y;
		$this->bFiltered = false;
	}

	public function GetIconURL()
	{
		return $this->GetProperty('icon_url', '');
	}
	
	public function GetLabel()
	{
		return $this->GetProperty('label', $this->sId);
	}
	
	public function GetWidth()
	{
		return max(32, 5*strlen($this->GetProperty('label'))); // approximation of the text's bounding box
	}
	
	public function GetHeight()
	{
		return 32;
	}
	
	public function Distance2(DisplayableNode $oNode)
	{
		$dx = $this->x - $oNode->x;
		$dy = $this->y - $oNode->y;
		
		$d2 = $dx*$dx + $dy*$dy - $this->GetHeight()*$this->GetHeight();
		if ($d2 < 40)
		{
			$d2 = 40;
		}
		return $d2;
	}
	
	public function Distance(DisplayableNode $oNode)
	{
		return sqrt($this->Distance2($oNode));
	}
	
	public function GetForRaphael()
	{
		$aNode = array();
		$aNode['shape'] = 'icon';
		$aNode['icon_url'] = $this->GetIconURL();
		$aNode['width'] = 32;
		$aNode['source'] = ($this->GetProperty('source') == true);
		$aNode['obj_class'] = get_class($this->GetProperty('object'));
		$aNode['obj_key'] = $this->GetProperty('object')->GetKey();
		$aNode['sink'] = ($this->GetProperty('sink') == true);
		$aNode['x'] = $this->x;
		$aNode['y']= $this->y;
		$aNode['label'] = $this->GetLabel();
		$aNode['id'] = $this->GetId();
		$fOpacity = ($this->GetProperty('is_reached') ? 1 : 0.4);
		$aNode['icon_attr'] = array('opacity' => $fOpacity);		
		$aNode['text_attr'] = array('opacity' => $fOpacity);		
		return $aNode;
	}
	
	public function RenderAsPDF(TCPDF $oPdf, DisplayableGraph $oGraph, $fScale)
	{
		$Alpha = 1.0;
		$oPdf->SetFillColor(200, 200, 200);
		$oPdf->setAlpha(1);
		
		$sIconUrl = $this->GetProperty('icon_url');
		$sIconPath = str_replace(utils::GetAbsoluteUrlModulesRoot(), APPROOT.'env-production/', $sIconUrl);
		
		if ($this->GetProperty('source'))
		{
			$oPdf->SetLineStyle(array('width' => 2*$fScale, 'cap' => 'round', 'join' => 'miter', 'dash' => 0, 'color' => array(204, 51, 51)));
			$oPdf->Circle($this->x * $fScale, $this->y * $fScale, 16 * 1.25 * $fScale, 0, 360, 'D');
		}
		else if ($this->GetProperty('sink'))
		{
			$oPdf->SetLineStyle(array('width' => 2*$fScale, 'cap' => 'round', 'join' => 'miter', 'dash' => 0, 'color' => array(51, 51, 204)));
			$oPdf->Circle($this->x * $fScale, $this->y * $fScale, 16 * 1.25 * $fScale, 0, 360, 'D');
		}
		
		if (!$this->GetProperty('is_reached'))
		{
			$sTempImageName = $this->CreateWhiteIcon($oGraph, $sIconPath);
			if ($sTempImageName != null)
			{
				$oPdf->Image($sTempImageName, ($this->x - 16)*$fScale, ($this->y - 16)*$fScale, 32*$fScale, 32*$fScale, 'PNG');
			}
			$Alpha = 0.4;
			$oPdf->setAlpha($Alpha);
		}
		
		$oPdf->Image($sIconPath, ($this->x - 16)*$fScale, ($this->y - 16)*$fScale, 32*$fScale, 32*$fScale);
		
		$oPdf->SetFont('dejavusans', '', 24 * $fScale, '', true);
		$width = $oPdf->GetStringWidth($this->GetProperty('label'));
		$height = $oPdf->GetStringHeight(1000, $this->GetProperty('label'));
		$oPdf->setAlpha(0.6 * $Alpha);
		$oPdf->SetFillColor(255, 255, 255);
		$oPdf->SetDrawColor(255, 255, 255);
		$oPdf->Rect($this->x*$fScale - $width/2, ($this->y + 18)*$fScale, $width, $height, 'DF');
		$oPdf->setAlpha($Alpha);
		$oPdf->SetTextColor(0, 0, 0);
		$oPdf->Text($this->x*$fScale - $width/2, ($this->y + 18)*$fScale, $this->GetProperty('label'));
	}
	
	/**
	 * Create a "whitened" version of the icon (retaining the transparency) to be used a background for masking the underlying lines
	 * @param string $sIconFile The path to the file containing the icon
	 * @return NULL|string The path to a temporary file containing the white version of the icon
	 */
	protected function CreateWhiteIcon(DisplayableGraph $oGraph, $sIconFile)
	{
		$aInfo = getimagesize($sIconFile);
		
		$im = null;
		switch($aInfo['mime'])
		{
			case 'image/png':
			if (function_exists('imagecreatefrompng'))
			{
				$im = imagecreatefrompng($sIconFile);
			}
			break;
			
			case 'image/gif':
			if (function_exists('imagecreatefromgif'))
			{
				$im = imagecreatefromgif($sIconFile);
			}
			break;
			
			case 'image/jpeg':
			case 'image/jpg':
			if (function_exists('imagecreatefromjpeg'))
			{
				$im = imagecreatefromjpeg($sIconFile);
			}
			break;
			
			default:
			return null;
			
		}
		if($im && imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 255))
		{
			$sTempImageName = $oGraph->GetTempImageName();
			imagesavealpha($im, true);
			imagepng($im, $sTempImageName);
			imagedestroy($im);
			return $sTempImageName;
		}
		else
		{
			return null;
		}
	}
	
	/**
	 * Group together (as a special kind of nodes) all the similar neighbours of the current node
	 * @param DisplayableGraph $oGraph
	 * @param int $iThresholdCount
	 * @param boolean $bDirectionUp
	 * @param boolean $bDirectionDown
	 */
	public function GroupSimilarNeighbours(DisplayableGraph $oGraph, $iThresholdCount, $bDirectionUp = false, $bDirectionDown = true)
	{
//echo "<p>".$this->GetProperty('label').":</p>";
		
		if ($this->GetProperty('grouped') === true) return;
		$this->SetProperty('grouped', true);
			
		if ($bDirectionDown)
		{
			$aNodesPerClass = array();
			foreach($this->GetOutgoingEdges() as $oEdge)
			{
				$oNode = $oEdge->GetSinkNode();
				
				if ($oNode->GetProperty('class') !== null)
				{
					$sClass = $oNode->GetProperty('class');
					if (($sClass!== null) && (!array_key_exists($sClass, $aNodesPerClass)))
					{
						$aNodesPerClass[$sClass] = array(
							'reached' => array(
								'count' => 0,
								'nodes' => array(),
								'icon_url' => $oNode->GetProperty('icon_url'),
							),
							'not_reached' => array(
								'count' => 0,
								'nodes' => array(),
								'icon_url' => $oNode->GetProperty('icon_url'),
							)
						);
					}
					$sKey = $oNode->GetProperty('is_reached') ? 'reached' : 'not_reached';
					if (!array_key_exists($oNode->GetId(), $aNodesPerClass[$sClass][$sKey]['nodes']))
					{
						$aNodesPerClass[$sClass][$sKey]['nodes'][$oNode->GetId()] = $oNode;
						$aNodesPerClass[$sClass][$sKey]['count'] += (int)$oNode->GetProperty('count', 1);
//echo "<p>New count: ".$aNodesPerClass[$sClass][$sKey]['count']."</p>";
					}
						
				}
				else
				{
					$oNode->GroupSimilarNeighbours($oGraph, $iThresholdCount, $bDirectionUp, $bDirectionDown);
				}
			}
			
			foreach($aNodesPerClass as $sClass => $aDefs)
			{
				foreach($aDefs as $sStatus => $aGroupProps)
				{
//echo "<p>$sClass/$sStatus: {$aGroupProps['count']} object(s), actually: ".count($aGroupProps['nodes'])."</p>";
					if (count($aGroupProps['nodes']) >= $iThresholdCount)
					{
						$oNewNode = new DisplayableGroupNode($oGraph, $this->GetId().'::'.$sClass);
						$oNewNode->SetProperty('label', 'x'.$aGroupProps['count']);
						$oNewNode->SetProperty('icon_url', $aGroupProps['icon_url']);
						$oNewNode->SetProperty('class', $sClass);
						$oNewNode->SetProperty('is_reached', ($sStatus == 'reached'));
						$oNewNode->SetProperty('count', $aGroupProps['count']);
						//$oNewNode->SetProperty('grouped', true);
						
						$oIncomingEdge = new DisplayableEdge($oGraph, $this->GetId().'-'.$oNewNode->GetId(), $this, $oNewNode);
										
						foreach($aGroupProps['nodes'] as $oNode)
						{
							foreach($oNode->GetIncomingEdges() as $oEdge)
							{
								if ($oEdge->GetSourceNode()->GetId() !== $this->GetId())
								{
									$oNewEdge = new DisplayableEdge($oGraph, $oEdge->GetId().'::'.$sClass, $oEdge->GetSourceNode(), $oNewNode);
								}
							}
							foreach($oNode->GetOutgoingEdges() as $oEdge)
							{
								$aOutgoing[] = $oEdge->GetSinkNode();
								try
								{
									$oNewEdge = new DisplayableEdge($oGraph, $oEdge->GetId().'::'.$sClass, $oNewNode, $oEdge->GetSinkNode());
								}
								catch(Exception $e)
								{
									// ignore this edge
								}
							}
							if ($oGraph->GetNode($oNode->GetId()))
							{
								$oGraph->_RemoveNode($oNode);
								$oNewNode->AddObject($oNode->GetProperty('object'));
							}
						}
						$oNewNode->GroupSimilarNeighbours($oGraph, $iThresholdCount, $bDirectionUp, $bDirectionDown);
					}
					else
					{
						foreach($aGroupProps['nodes'] as $oNode)
						{
							$oNode->GroupSimilarNeighbours($oGraph, $iThresholdCount, $bDirectionUp, $bDirectionDown);
						}
					}
				}
			}
		}
	}
}

class DisplayableRedundancyNode extends DisplayableNode
{
	public function GetWidth()
	{
		return 24;
	}
	
	public function GetForRaphael()
	{
		$aNode = array();
		$aNode['shape'] = 'disc';
		$aNode['icon_url'] = $this->GetIconURL();
		$aNode['source'] = ($this->GetProperty('source') == true);
		$aNode['width'] = $this->GetWidth();
		$aNode['x'] = $this->x;
		$aNode['y']= $this->y;
		$aNode['label'] = $this->GetLabel();
		$aNode['id'] = $this->GetId();	
		$fDiscOpacity = ($this->GetProperty('is_reached') ? 1 : 0.2);
		$aNode['disc_attr'] = array('stroke-width' => 3, 'stroke' => '#000', 'fill' => '#c33', 'opacity' => $fDiscOpacity);
		$fTextOpacity = ($this->GetProperty('is_reached') ? 1 : 0.4);
		$aNode['text_attr'] = array('fill' => '#fff', 'opacity' => $fTextOpacity);		
		return $aNode;
	}

	public function RenderAsPDF(TCPDF $oPdf, DisplayableGraph $oGraph, $fScale)
	{
		$oPdf->SetAlpha(1);
		$oPdf->SetFillColor(200, 0, 0);
		$oPdf->SetDrawColor(0, 0, 0);
		$oPdf->Circle($this->x*$fScale, $this->y*$fScale, 16*$fScale, 0, 360, 'DF');

		$oPdf->SetTextColor(255, 255, 255);
		$oPdf->SetFont('dejavusans', '', 28 * $fScale, '', true);
		$sLabel  = (string)$this->GetProperty('label');
		$width = $oPdf->GetStringWidth($sLabel, 'dejavusans', 'B', 24*$fScale);
		$height = $oPdf->GetStringHeight(1000, $sLabel);
		$xPos = (float)$this->x*$fScale - $width/2;
		$yPos = (float)$this->y*$fScale - $height/2;
		
		$oPdf->SetXY(($this->x - 16)*$fScale, ($this->y - 16)*$fScale);
		
		$oPdf->Cell(32*$fScale, 32*$fScale, $sLabel, 0, 0, 'C', 0, '', 0, false, 'T', 'C');
	}
	
	/**
	 * @see DisplayableNode::GroupSimilarNeighbours()
	 */
	public function GroupSimilarNeighbours(DisplayableGraph $oGraph, $iThresholdCount, $bDirectionUp = false, $bDirectionDown = true)
	{
		parent::GroupSimilarNeighbours($oGraph, $iThresholdCount, $bDirectionUp, $bDirectionDown);
		
		if ($bDirectionUp)
		{
			$aNodesPerClass = array();
			foreach($this->GetIncomingEdges() as $oEdge)
			{
				$oNode = $oEdge->GetSourceNode();
		
				if (($oNode->GetProperty('class') !== null) && (!$oNode->GetProperty('is_reached')))
				{
					$sClass = $oNode->GetProperty('class');
					if (!array_key_exists($sClass, $aNodesPerClass))
					{
						$aNodesPerClass[$sClass] = array('reached' => array(), 'not_reached' => array());
					}
					$aNodesPerClass[$sClass][$oNode->GetProperty('is_reached') ? 'reached' : 'not_reached'][] = $oNode;
				}
				else
				{
					//$oNode->GroupSimilarNeighbours($oGraph, $iThresholdCount, $bDirectionUp, $bDirectionDown);
				}
			}

			foreach($aNodesPerClass as $sClass => $aDefs)
			{
				foreach($aDefs as $sStatus => $aNodes)
				{
//echo "<p>".$this->GetId().' has '.count($aNodes)." neighbours of class $sClass in status $sStatus\n";
					if (count($aNodes) >= $iThresholdCount)
					{
						$oNewNode = new DisplayableGroupNode($oGraph, '-'.$this->GetId().'::'.$sClass.'/'.$sStatus);
						$oNewNode->SetProperty('label', 'x'.count($aNodes));
						$oNewNode->SetProperty('icon_url', $aNodes[0]->GetProperty('icon_url'));
						$oNewNode->SetProperty('is_reached', $aNodes[0]->GetProperty('is_reached'));
							
						$oOutgoingEdge = new DisplayableEdge($oGraph, '-'.$this->GetId().'-'.$oNewNode->GetId().'/'.$sStatus, $oNewNode, $this);
		
						foreach($aNodes as $oNode)
						{
							foreach($oNode->GetIncomingEdges() as $oEdge)
							{
								$oNewEdge = new DisplayableEdge($oGraph, '-'.$oEdge->GetId().'::'.$sClass, $oEdge->GetSourceNode(), $oNewNode);
							}
							foreach($oNode->GetOutgoingEdges() as $oEdge)
							{
								if ($oEdge->GetSinkNode()->GetId() !== $this->GetId())
								{
									$aOutgoing[] = $oEdge->GetSinkNode();
									$oNewEdge = new DisplayableEdge($oGraph, '-'.$oEdge->GetId().'::'.$sClass.'/'.$sStatus, $oNewNode, $oEdge->GetSinkNode());
								}
							}
//echo "<p>Replacing ".$oNode->GetId().' by '.$oNewNode->GetId()."\n";
							$oGraph->_RemoveNode($oNode);
							$oNewNode->AddObject($oNode->GetProperty('object'));
						}
						//$oNewNode->GroupSimilarNeighbours($oGraph, $iThresholdCount, $bDirectionUp, $bDirectionDown);
					}
					else
					{
						foreach($aNodes as $oNode)
						{
							//$oNode->GroupSimilarNeighbours($oGraph, $iThresholdCount, $bDirectionUp, $bDirectionDown);
						}
					}
				}
			}
		}
	}
}

class DisplayableEdge extends GraphEdge
{
	public function RenderAsPDF(TCPDF $oPdf, DisplayableGraph $oGraph, $fScale)
	{
		$xStart = $this->GetSourceNode()->x * $fScale;
		$yStart = $this->GetSourceNode()->y * $fScale;
		$xEnd = $this->GetSinkNode()->x * $fScale;
		$yEnd = $this->GetSinkNode()->y * $fScale;
		
		$bReached = ($this->GetSourceNode()->GetProperty('is_reached') && $this->GetSinkNode()->GetProperty('is_reached'));
		
		$oPdf->setAlpha(1);
		if ($bReached)
		{
			$aColor = array(100, 100, 100);
		}
		else
		{
			$aColor = array(200, 200, 200);
		}
		$oPdf->SetLineStyle(array('width' => 2*$fScale, 'cap' => 'round', 'join' => 'miter', 'dash' => 0, 'color' => $aColor));
		$oPdf->Line($xStart, $yStart, $xEnd, $yEnd);
		
		
		$vx = $xEnd - $xStart;
		$vy = $yEnd - $yStart;
		$l = sqrt($vx*$vx + $vy*$vy);
		$vx = $vx / $l;
		$vy = $vy / $l;
		$ux = -$vy;
		$uy = $vx;
		$lPos = max($l/2, $l - 40*$fScale);
		$iArrowSize = 5*$fScale;
		
		$x = $xStart  + $lPos * $vx;
		$y = $yStart + $lPos * $vy;
		$oPdf->Line($x, $y, $x + $iArrowSize * ($ux-$vx), $y + $iArrowSize * ($uy-$vy));
		$oPdf->Line($x, $y, $x - $iArrowSize * ($ux+$vx), $y - $iArrowSize * ($uy+$vy));		
	}
}

class DisplayableGroupNode extends DisplayableNode
{
	protected $aObjects;
	
	public function __construct(SimpleGraph $oGraph, $sId, $x = 0, $y = 0)
	{
		parent::__construct($oGraph, $sId, $x, $y);
		$this->aObjects = array();
	}
	
	public function AddObject(DBObject $oObj)
	{
		$this->aObjects[$oObj->GetKey()] = $oObj;
	}
	
	public function GetObjects()
	{
		return $this->aObjects;
	}
	
	public function GetWidth()
	{
		return 50;
	}

	public function GetForRaphael()
	{
		$aNode = array();
		$aNode['shape'] = 'group';
		$aNode['icon_url'] = $this->GetIconURL();
		$aNode['source'] = ($this->GetProperty('source') == true);
		$aNode['width'] = $this->GetWidth();
		$aNode['x'] = $this->x;
		$aNode['y']= $this->y;
		$aNode['label'] = $this->GetLabel();
		$aNode['id'] = $this->GetId();
		$aNode['group_index'] = $this->GetProperty('group_index'); // if supplied
		$fDiscOpacity = ($this->GetProperty('is_reached') ? 1 : 0.2);
		$fTextOpacity = ($this->GetProperty('is_reached') ? 1 : 0.4);
		$aNode['icon_attr'] = array('opacity' => $fTextOpacity);
		$aNode['disc_attr'] = array('stroke-width' => 3, 'stroke' => '#000', 'fill' => '#fff', 'opacity' => $fDiscOpacity);
		$aNode['text_attr'] = array('fill' => '#000', 'opacity' => $fTextOpacity);
		return $aNode;
	}
	
	public function RenderAsPDF(TCPDF $oPdf, DisplayableGraph $oGraph, $fScale)
	{
		$bReached = $this->GetProperty('is_reached');
		$oPdf->SetFillColor(255, 255, 255);
		if ($bReached)
		{
			$aBorderColor = array(100, 100, 100);
		}
		else
		{
			$aBorderColor = array(200, 200, 200);
		}
		$oPdf->SetLineStyle(array('width' => 2*$fScale, 'cap' => 'round', 'join' => 'miter', 'dash' => 0, 'color' => $aBorderColor));
		
		$sIconUrl = $this->GetProperty('icon_url');
		$sIconPath = str_replace(utils::GetAbsoluteUrlModulesRoot(), APPROOT.'env-production/', $sIconUrl);
		$oPdf->SetAlpha(1);
		$oPdf->Circle($this->x*$fScale, $this->y*$fScale, $this->GetWidth() / 2 * $fScale, 0, 360, 'DF');
		
		if ($bReached)
		{
			$oPdf->SetAlpha(1);
		}
		else
		{
			$oPdf->SetAlpha(0.4);
		}
		$oPdf->Image($sIconPath, ($this->x - 17)*$fScale, ($this->y - 17)*$fScale, 16*$fScale, 16*$fScale);
		$oPdf->Image($sIconPath, ($this->x + 1)*$fScale, ($this->y - 17)*$fScale, 16*$fScale, 16*$fScale);
		$oPdf->Image($sIconPath, ($this->x -8)*$fScale, ($this->y +1)*$fScale, 16*$fScale, 16*$fScale);
		$oPdf->SetFont('dejavusans', '', 24 * $fScale, '', true);
		$width = $oPdf->GetStringWidth($this->GetProperty('label'));
		$oPdf->SetTextColor(0, 0, 0);
		$oPdf->Text($this->x*$fScale - $width/2, ($this->y + 25)*$fScale, $this->GetProperty('label'));
	}
}

/**
 * A Graph that can be displayed interactively using Raphael JS or saved as a PDF document
 */
class DisplayableGraph extends SimpleGraph
{
	protected $sDirection;
	protected $aTempImages;
	
	public function __construct()
	{
		parent::__construct();
		$this->aTempImages = array();
	}
	
	public function GetTempImageName()
	{
		$sNewTempName = tempnam(APPROOT.'data', 'img-');
		$this->aTempImages[] = $sNewTempName;
		return $sNewTempName;
	}
	
	public function __destruct()
	{
		foreach($this->aTempImages as $sTempFile)
		{
			@unlink($sTempFile);
		}
	}
	
	/**
	 * Build a DisplayableGraph from a RelationGraph
	 * @param RelationGraph $oGraph
	 * @param number $iGroupingThreshold
	 * @param string $bDirectionDown
	 * @return DisplayableGraph
	 */
	public static function FromRelationGraph(RelationGraph $oGraph, $iGroupingThreshold = 20, $bDirectionDown = true)
	{
		$oNewGraph = new DisplayableGraph();
		
		$oNodesIter = new RelationTypeIterator($oGraph, 'Node');
		foreach($oNodesIter as $oNode)
		{
			switch(get_class($oNode))
			{
				case 'RelationObjectNode':				
				$oNewNode = new DisplayableNode($oNewGraph, $oNode->GetId(), 0, 0);
				
				if ($oNode->GetProperty('source'))
				{
					$oNewNode->SetProperty('source', true);
				}
				if ($oNode->GetProperty('sink'))
				{
					$oNewNode->SetProperty('sink', true);
				}
				$oObj = $oNode->GetProperty('object');
				$oNewNode->SetProperty('class', get_class($oObj));
				$oNewNode->SetProperty('object', $oObj);
				$oNewNode->SetProperty('icon_url', $oObj->GetIcon(false));
				$oNewNode->SetProperty('label', $oObj->GetRawName());
				$oNewNode->SetProperty('is_reached', $bDirectionDown ? $oNode->GetProperty('is_reached') : true); // When going "up" is_reached does not matter
				$oNewNode->SetProperty('developped', $oNode->GetProperty('developped'));
				break;
				
				default:
				$oNewNode = new DisplayableRedundancyNode($oNewGraph, $oNode->GetId(), 0, 0);
				$oNewNode->SetProperty('label', $oNode->GetProperty('min_up'));
				$oNewNode->SetProperty('is_reached', true);
			}
		}
		$oEdgesIter = new RelationTypeIterator($oGraph, 'Edge');
		foreach($oEdgesIter as $oEdge)
		{
			$oSourceNode = $oNewGraph->GetNode($oEdge->GetSourceNode()->GetId());
			$oSinkNode = $oNewGraph->GetNode($oEdge->GetSinkNode()->GetId());
			$oNewEdge = new DisplayableEdge($oNewGraph, $oEdge->GetId(), $oSourceNode, $oSinkNode);
		}
		
		// Remove duplicate edges between two nodes
		$oEdgesIter = new RelationTypeIterator($oNewGraph, 'Edge');
		$aEdgeKeys = array();
		foreach($oEdgesIter as $oEdge)
		{
			$sSourceId =  $oEdge->GetSourceNode()->GetId();
			$sSinkId = $oEdge->GetSinkNode()->GetId();
			if ($sSourceId == $sSinkId)
			{
				// Remove self referring edges
				$oNewGraph->_RemoveEdge($oEdge);
			}
			else
			{
				$sKey = $sSourceId.'//'.$sSinkId;
				if (array_key_exists($sKey, $aEdgeKeys))
				{
					// Remove duplicate edges
					$oNewGraph->_RemoveEdge($oEdge);
				}
				else
				{
					$aEdgeKeys[$sKey] = true;
				}
			}
		}
		
		$iNbGrouping = 1;
		//for($iter=0; $iter<$iNbGrouping; $iter++)
		{
			$oNodesIter = new RelationTypeIterator($oNewGraph, 'Node');
			foreach($oNodesIter as $oNode)
			{
				if ($oNode->GetProperty('source'))
				{
					$oNode->GroupSimilarNeighbours($oNewGraph, $iGroupingThreshold, true, true);
				}
			}
		}
		
		// Remove duplicate edges between two nodes
		$oEdgesIter = new RelationTypeIterator($oNewGraph, 'Edge');
		$aEdgeKeys = array();
		foreach($oEdgesIter as $oEdge)
		{
			$sSourceId =  $oEdge->GetSourceNode()->GetId();
			$sSinkId = $oEdge->GetSinkNode()->GetId();
			if ($sSourceId == $sSinkId)
			{
				// Remove self referring edges
				$oNewGraph->_RemoveEdge($oEdge);
			}
			else
			{
				$sKey = $sSourceId.'//'.$sSinkId;
				if (array_key_exists($sKey, $aEdgeKeys))
				{
					// Remove duplicate edges
					$oNewGraph->_RemoveEdge($oEdge);
				}
				else
				{
					$aEdgeKeys[$sKey] = true;
				}
			}
		}

		return $oNewGraph;
	}
	
	/**
	 * Initializes the positions by rendering using Graphviz in xdot format
	 * and parsing the output.
	 * @throws Exception
	 */
	public function InitFromGraphviz()
	{
		$sDot = $this->DumpAsXDot();
		if (strpos($sDot, 'digraph') === false)
		{
			throw new Exception($sDot);
		}
		$sDot = preg_replace('/.*label=.*,/', '', $sDot); // Get rid of label lines since they may contain weird characters than can break the split and pattern matching below
		
		$aChunks = explode(";", $sDot);
		foreach($aChunks as $sChunk)
		{
			//echo "<p>$sChunk</p>";
			if(preg_match('/"([^"]+)".+pos="([0-9\\.]+),([0-9\\.]+)"/ms', $sChunk, $aMatches))
			{
				$sId = $aMatches[1];
				$xPos = $aMatches[2];
				$yPos = $aMatches[3];
				
				$oNode = $this->GetNode($sId);
				$oNode->x = (float)$xPos;
				$oNode->y = (float)$yPos;
				
				//echo "<p>$sId at $xPos,$yPos</p>";
			}
			else
			{
				//echo "<p>No match</p>";
			}
		}
	}
	
	public function GetBoundingBox()
	{
		$xMin = null;
		$xMax = null;
		$yMin = null;
		$yMax = null;
		$oIterator = new RelationTypeIterator($this, 'Node');
		foreach($oIterator as $sId => $oNode)
		{
			if ($xMin === null) // First element in the loop
			{
				$xMin = $oNode->x - $oNode->GetWidth();
				$xMax = $oNode->x + $oNode->GetWidth();
				$yMin = $oNode->y - $oNode->GetHeight();
				$yMax = $oNode->y + $oNode->GetHeight();
			}
			else
			{
				$xMin = min($xMin, $oNode->x - $oNode->GetWidth() / 2);
				$xMax = max($xMax, $oNode->x + $oNode->GetWidth() / 2);
				$yMin = min($yMin, $oNode->y - $oNode->GetHeight() / 2);
				$yMax = max($yMax, $oNode->y + $oNode->GetHeight() / 2);
			}
		}
		
		return array('xmin' => $xMin, 'xmax' => $xMax, 'ymin' => $yMin, 'ymax' => $yMax);
	}
	
	function Translate($dx, $dy)
	{
		$oIterator = new RelationTypeIterator($this, 'Node');
		foreach($oIterator as $sId => $oNode)
		{
			$oNode->x += $dx;
			$oNode->y += $dy;
		}		
	}
	
	public function UpdatePositions($aPositions)
	{
		foreach($aPositions as $sNodeId => $aPos)
		{
			$oNode = $this->GetNode($sNodeId);
			if ($oNode != null)
			{
				$oNode->x = $aPos['x'];
				$oNode->y = $aPos['y'];
			}
		}
	}
	
	/**
	 * Renders as a suite of Javascript instructions to display the graph using the simple_graph widget
	 * @param WebPage $oP
	 * @param string $sId
	 * @param string $sExportAsPdfURL
	 * @param string $sExportAsDocumentURL
	 * @param string $sDrillDownURL
	 */
	function RenderAsRaphael(WebPage $oP, $sId = null, $sExportAsPdfURL, $sExportAsDocumentURL, $sDrillDownURL)
	{
		if ($sId == null)
		{
			$sId = 'graph';
		}
		$oP->add('<div id="'.$sId.'" class="simple-graph"></div>');
		$aParams = array(
			'export_as_pdf' => array('url' => $sExportAsPdfURL, 'label' => Dict::S('UI:Relation:ExportAsPDF')),
			'export_as_document' => array('url' => $sExportAsDocumentURL, 'label' => Dict::S('UI:Relation:ExportAsDocument')),
			'drill_down' => array('url' => $sDrillDownURL, 'label' => Dict::S('UI:Relation:DrillDown')),
			'labels' => array(
				'export_pdf_title' => Dict::S('UI:Relation:PDFExportOptions'),
				'export' => Dict::S('UI:Relation:PDFExportOptions'),
				'cancel' => Dict::S('UI:Button:Cancel'),
			),
			'page_format' => array(
				'label' => Dict::S('UI:Relation:PDFExportPageFormat'),
				'values' => array(
					'A3' => Dict::S('UI:PageFormat_A3'),
					'A4' => Dict::S('UI:PageFormat_A4'),
					'Letter' => Dict::S('UI:PageFormat_Letter'),
				),
			),
			'page_orientation' => array(
				'label' => Dict::S('UI:Relation:PDFExportPageOrientation'),
				'values' => array(
					'P' => Dict::S('UI:PageOrientation_Portrait'),
					'L' => Dict::S('UI:PageOrientation_Landscape'),
				),
			),
		);
		$oP->add_ready_script("var oGraph = $('#$sId').simple_graph(".json_encode($aParams).");");
		
		$oIterator = new RelationTypeIterator($this, 'Node');
		foreach($oIterator as $sId => $oNode)
		{
			$aNode = $oNode->GetForRaphael();
			$sJSNode = json_encode($aNode);
			$oP->add_ready_script("oGraph.simple_graph('add_node', $sJSNode);");
		}
		$oIterator = new RelationTypeIterator($this, 'Edge');
		foreach($oIterator as $sId => $oEdge)
		{
			$aEdge = array();
			$aEdge['id'] = $oEdge->GetId();
			$aEdge['source_node_id'] = $oEdge->GetSourceNode()->GetId();
			$aEdge['sink_node_id'] = $oEdge->GetSinkNode()->GetId();
			$fOpacity = ($oEdge->GetSinkNode()->GetProperty('is_reached') && $oEdge->GetSourceNode()->GetProperty('is_reached') ? 1 : 0.2);
			$aEdge['attr'] = array('opacity' => $fOpacity, 'stroke' => '#000');
			$sJSEdge = json_encode($aEdge);
			$oP->add_ready_script("oGraph.simple_graph('add_edge', $sJSEdge);");
		}
		
		$oP->add_ready_script("oGraph.simple_graph('draw');");
	}

	/**
	 * Renders as JSON string suitable for loading into the simple_graph widget
	 */
	function GetAsJSON()
	{
		$aData = array('nodes' => array(), 'edges' => array());
		$iGroupIdx = 0;
		$oIterator = new RelationTypeIterator($this, 'Node');
		foreach($oIterator as $sId => $oNode)
		{
			if ($oNode instanceof DisplayableGroupNode)
			{
				$aGroups[] = $oNode->GetObjects();
				$oNode->SetProperty('group_index', $iGroupIdx);
				$iGroupIdx++;
			}
			$aData['nodes'][] = $oNode->GetForRaphael();
		}
		
		$oIterator = new RelationTypeIterator($this, 'Edge');
		foreach($oIterator as $sId => $oEdge)
		{
			$aEdge = array();
			$aEdge['id'] = $oEdge->GetId();
			$aEdge['source_node_id'] = $oEdge->GetSourceNode()->GetId();
			$aEdge['sink_node_id'] = $oEdge->GetSinkNode()->GetId();
			$fOpacity = ($oEdge->GetSinkNode()->GetProperty('is_reached') && $oEdge->GetSourceNode()->GetProperty('is_reached') ? 1 : 0.2);
			$aEdge['attr'] = array('opacity' => $fOpacity, 'stroke' => '#000');
			$aData['edges'][] = $aEdge;
		}
	
		return json_encode($aData);
	}
	
	/**
	 * Renders the graph as a PDF file
	 * @param WebPage $oP The page for the ouput of the PDF
	 * @param string $sTitle The title of the PDF
	 * @param string $sPageFormat The page format: A4, A3, Letter...
	 * @param string $sPageOrientation The orientation of the page (L = Landscape, P = Portrait)
	 */
	function RenderAsPDF(WebPage $oP, $sTitle = 'Untitled', $sPageFormat = 'A4', $sPageOrientation = 'P')
	{
		require_once(APPROOT.'lib/tcpdf/tcpdf.php');
		$oPdf = new TCPDF($sPageOrientation, 'mm', $sPageFormat, true, 'UTF-8', false);
		
		// set document information
		$oPdf->SetCreator(PDF_CREATOR);
		$oPdf->SetAuthor('iTop');
		$oPdf->SetTitle($sTitle);
		
		$oPdf->setFontSubsetting(true);
		
		// Set font
		// dejavusans is a UTF-8 Unicode font, if you only need to
		// print standard ASCII chars, you can use core fonts like
		// helvetica or times to reduce file size.
		$oPdf->SetFont('dejavusans', '', 14, '', true);
		
		// set auto page breaks
		$oPdf->SetAutoPageBreak(false);
		
		// Add a page
		// This method has several options, check the source code documentation for more information.
		$oPdf->AddPage();
		
		$aBB = $this->GetBoundingBox();
		$this->Translate(-$aBB['xmin'], -$aBB['ymin']);
		
		if ($sPageOrientation == 'P')
		{
			// Portrait mode
			$fHMargin = 10; // mm
			$fVMargin = 15; // mm
		}
		else
		{
			// Landscape mode
			$fHMargin = 15; // mm
			$fVMargin = 10; // mm
		}
		
		$fPageW = $oPdf->getPageWidth() - 2 * $fHMargin;
		$fPageH = $oPdf->getPageHeight() - 2 * $fVMargin;
		
		$w = $aBB['xmax'] - $aBB['xmin']; 
		$h = $aBB['ymax'] - $aBB['ymin'] + 10; // Extra space for the labels which may appear "below" the icons
		
		$fScale = min($fPageW / $w, $fPageH / $h);
		$dx = ($fPageW - $fScale * $w) / 2;
		$dy = ($fPageH - $fScale * $h) / 2;
		
		$this->Translate(($fHMargin + $dx)/$fScale, ($fVMargin + $dy)/$fScale);
		
		$oIterator = new RelationTypeIterator($this, 'Edge');
		foreach($oIterator as $sId => $oEdge)
		{
			$oEdge->RenderAsPDF($oPdf, $this, $fScale);
		}

		$oIterator = new RelationTypeIterator($this, 'Node');
		foreach($oIterator as $sId => $oNode)
		{
			$oNode->RenderAsPDF($oPdf, $this, $fScale);
		}
		
		$oP->add($oPdf->Output('iTop.pdf', 'S'));	
	}
	
}