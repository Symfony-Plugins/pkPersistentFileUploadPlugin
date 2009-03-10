<?php

class pkPersistentFileUploadIframeForm extends sfForm
{
  private $persistid = null;
  public function __construct($persistid)
  {
    $this->persistid = $persistid;
    parent::__construct();
  }
  public function configure()
  {
    $this->setWidget("file",
      new pkWidgetFormInputFilePersistent(
        array('iframe-content' => true, 'persistid' => $this->persistid)));

    // This does not really mean we're accepting the file with no
    // further validation! The uploaded file will still get validated 
    // fully when the real form is submitted.

    // So why do we need the validator at all? Because it takes care
    // of silently stashing the file in the persistent uploads cache
    // for this persistid, where the real form will be able to find it.

    $this->setValidator('file', new pkValidatorFilePersistent());

    $this->widgetSchema->setNameFormat('file[%s]');
  }
}
