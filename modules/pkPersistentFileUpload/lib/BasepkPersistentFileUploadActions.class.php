<?php

/**
 * Base actions for the pkPersistentFileUploadPlugin pkPersistentFileUpload module.
 * 
 * @package     pkPersistentFileUploadPlugin
 * @subpackage  pkPersistentFileUpload
 * @author      Tom Boutell
 * @version     SVN: $Id: BaseActions.class.php 12534 2008-11-01 13:38:27Z boutell $
 */
abstract class BasepkPersistentFileUploadActions extends sfActions
{
  public function executeIframe(sfRequest $request)
  {
    $persistid = $request->getParameter('persistid');
    $this->forward404Unless(
      pkValidatorFilePersistent::validPersistId($persistid));
    $info = pkValidatorFilePersistent::getFileInfo($persistid);
    $this->forward404Unless($info);
    $options = $info['options'];
    unset($options['iframe']);
    $options['iframe-content'] = true;
    $this->persistid = $persistid;

    // You really don't want your full page layout here. Shutting off
    // the layout entirely (the default) is a good first approximation
    // but might want something that brings in styles for the file
    // upload element.
    $this->setLayout(sfConfig::get('sf_persistent_upload_iframe_layout', false));
    $form = new pkPersistentFileUploadIframeForm($persistid);
    if ($request->isMethod('post'))
    {
      $form->bind($request->getParameter('file[]'));
      if ($form->isValid())
      {
        // That's nice. We're validating just to invoke the
        // persistent upload cache.
      }
    }
    $this->form = $form;
  }
}
