<?php
/**
* Papaya PDF document
*
* @copyright 2002-2009 by papaya Software GmbH - All rights reserved.
* @link      http://www.papaya-cms.com/
* @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License, version 2
*
* You can redistribute and/or modify this script under the terms of the GNU General Public
* License (GPL) version 2, provided that the copyright and license notes, including these
* lines, remain unmodified. papaya is distributed in the hope that it will be useful, but
* WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
* FOR A PARTICULAR PURPOSE.
*
* @package Papaya-Modules
* @subpackage Free-PDF
* @version $Id: papaya_pdf_document.php 39522 2014-03-05 19:42:35Z weinert $
*/

/**
* Papaya PDF document
*
* @package Papaya-Modules
* @subpackage Free-PDF
*/
class papaya_pdf_document {

  /**
  * local path for binary files (pdf background templates, fonts, ...)
  * @var string
  */
  var $pathDataFiles = 'templates/';

  /**
  * callback function for image handling -
  * needs to return an absolute locale filename
  * @var mixed
  */
  var $callbackImage = NULL;

  /**
  * callback function for link handling -
  * needs to return an absolute url or NULL
  * @var mixed
  */
  var $callbackLink = NULL;

  /**
  * papaya_pdf object
  * @var papaya_pdf $pdf
  */
  var $pdf = NULL;

  /**
  * Output charset
  * @var string $outputCharset
  */
  var $outputCharset = 'UTF-8';

  /**
   * @var papaya_pdf_options
   */
  private $pdfConf = NULL;

  /**
   * Parse page
   *
   * @see FPDF::Output()
   *
   * @param $xmlDocument
   * @param string $title
   * @access public
   * @return string
   */
  function get($xmlDocument, $title = NULL) {
    $xpath = new DOMXPath($xmlDocument);

    $this->pdfConf = new papaya_pdf_options();
    $this->pdfConf->pathDataFiles = $this->pathDataFiles;
    $this->pdf = new papaya_pdf($this, $this->pdfConf);

    if (isset($xmlDocument->documentElement)) {
      if ($charset = $xpath->evaluate('string(/*/@charset)')) {
        $this->outputCharset = $charset;
      }
      $nodeCover = NULL;
      $nodeIndex = NULL;
      $nodeContent = NULL;
      $nodeFinal = NULL;
      $nodeHeader = NULL;
      $nodeFooter = NULL;

      foreach ($xpath->evaluate('/*/*') as $childNode) {
        switch ($childNode->nodeName) {
        case 'layout':
          $this->pdfConf->initialize($this, $childNode);
          $this->pdfConf->pdfInitialize($this->pdf);
          break;
        case 'cover':
          $nodeCover = $childNode;
          break;
        case 'toc':
          $nodeIndex = $childNode;
          break;
        case 'content':
          $nodeContent = $childNode;
          break;
        case 'final':
          $nodeFinal = $childNode;
          break;
        case 'header':
          $nodeHeader = $childNode;
          break;
        case 'footer':
          $nodeFooter = $childNode;
          break;
        }
      }
      $this->outputPageNamed($nodeCover, 'cover');
      $this->outputPage($nodeContent);
      $this->outputPageNamed($nodeFinal, 'final');
      $this->outputPageIndex($nodeIndex, isset($nodeCover));
      $this->outputHeaderAndFooter($nodeHeader, $nodeFooter);
    }
    $docTitle = (empty($title)) ? 'PDF document' : $title;
    return $this->pdf->Output($docTitle, 'S');
  }

  /**
  * Output the table of contents
  * @param DOMElement $node
  * @param boolean $hasCover
  * @return void
  */
  function outputPageIndex(DOMElement $node, $hasCover) {
    if (isset($node)) {
      $position = $hasCover ? 2 : 1;
      $this->pdfConf->pdfInsertPage($this->pdf, $position, 'toc', TRUE);
      if ($node->hasAttribute('title')) {
        $this->pdf->setPageTitle(
          $node->getAttribute('title'),
          $node->getAttribute('subtitle')
        );
      }
      $this->outputDataChildren($node);
      $this->pdf->writeIndex($node->attributes);
    }
  }

  /**
  * Output page named
  *
  * @param DOMElement $node xml subnode reference
  * @param string $pageName page name
  * @access public
  */
  function outputPageNamed(DOMElement $node, $pageName) {
    if (isset($node)) {
      $this->pdfConf->pdfAddPage($this->pdf, $pageName, TRUE);
      $xpath = new DOMXPath($node->ownerDocument);
      /** @var DOMElement $childNode */
      foreach ($xpath->evaluate('element[@id != ""]', $node) as $childNode) {
        if (($for = $childNode->getAttribute('id')) &&
            $this->pdfConf->pdfInitElement($this->pdf, $pageName, $for)) {
          $this->pdf->openElement($childNode->attributes);
          $this->outputDataChildren($childNode);
          $this->pdf->closeElement($childNode->attributes);
        }
      }
    }
  }

  /**
  * Output page
  *
  * @param DOMElement $node
  * @access public
  */
  function outputPage(DOMElement $node) {
    if ($node->hasChildNodes()) {
      $this->pdf->breakBeforeNextSection = TRUE;
      $xpath = new DOMXPath($node->ownerDocument);
      /** @var DOMElement $childNode */
      foreach ($xpath->evaluate('section|import[@file]', $node) as $childNode) {
        if ($childNode->nodeName == 'section') {
          $this->pdf->openSection($childNode->attributes);
          $this->outputDataChildren($childNode);
          $this->pdf->closeSection($childNode->attributes);
        } elseif ($childNode->nodeName == 'import') {
          if (($fileName = $childNode->getAttribute('file')) &&
              file_exists($this->pathDataFiles.$fileName) &&
              is_file($this->pathDataFiles.$fileName) &&
              is_readable($this->pathDataFiles.$fileName)) {
            $this->pdf->importPages($childNode, $this->pathDataFiles.$fileName);
          }
        }
      }
    }
  }

  /**
  * Output header and footer elements
  * @param DOMElement $headerNode
  * @param DOMElement $footerNode
  * @return void
  */
  function outputHeaderAndFooter(DOMElement $headerNode, DOMElement $footerNode) {
    foreach ($this->pdf->pageInfos as $pageNumber => $pageInfos) {
      $this->pdf->gotoPageColumn($pageNumber, 1);
      if (!empty($pageInfos['template'])) {
        $this->pdfConf->pdfInitPageMeasures($this->pdf, $pageInfos['template']);
        $this->pdfConf->pdfOutputDocumentElement(
          $this->pdf, $pageInfos['template'], 'header', $headerNode
        );
        $this->pdfConf->pdfOutputDocumentElement(
          $this->pdf, $pageInfos['template'], 'footer', $footerNode
        );
      }
    }
  }

  /**
  * Output data children
  *
  * @param DOMElement $node
  * @access public
  */
  function outputDataChildren(DOMElement $node) {
    if ($node->hasChildNodes()) {
      foreach ($node->childNodes as $childNode) {
        if ($childNode instanceof DOMText) {
          $str = $this->filterString($childNode->nodeValue, TRUE);
          $this->pdf->writeText($str);
        } elseif ($childNode instanceof DOMElement) {
          if ($childNode->nodeName == 'img') {
            if ($fileName = $this->getImageForPDF($childNode->attributes)) {
              $this->pdf->writeImage($fileName, $childNode->attributes);
            }
          } else {
            $this->pdf->openTag($childNode->nodeName, $childNode->attributes);
            // go into childnodes
            if ($childNode->hasChildNodes()) {
              $this->outputDataChildren($childNode);
            }
            $this->pdf->closeTag($childNode->nodeName, $childNode->attributes);
          }
        }
      }
    }
  }

  /**
  * Get image for PDF
  *
  * @param DOMNamedNodeMap|array $imgAttr image attributes
  * @access public
  * @return mixed NULL or image string
  */
  function getImageForPDF($imgAttr) {
    $imgAttr = papaya_pdf::simplify($imgAttr);
    if (isset($this->callbackImage)) {
      return call_user_func($this->callbackImage, $imgAttr);
    }
    return NULL;
  }

  /**
  * Get link for PDF
  *
  * @param DOMNamedNodeMap|array $linkAttr link attributes
  * @access public
  * @return mixed NULL or href string
  */
  function getLinkForPDF($linkAttr) {
    $linkAttr = papaya_pdf::simplify($linkAttr);
    if (isset($this->callbackLink)) {
      return call_user_func($this->callbackLink, $linkAttr);
    }
    return NULL;
  }


  /**
  * Filter string to UTF-8
  *
  * @param string $str
  * @access public
  * @return string $result
  */
  function filterString($str) {
    $result = str_replace(array("\r\n", "\n\r", "\r", "\n"), " ", $str);
    return $result;
  }
}
