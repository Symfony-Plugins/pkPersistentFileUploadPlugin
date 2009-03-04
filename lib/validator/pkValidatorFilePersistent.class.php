<?php

// Copyright 2009, P'unk Ave LLC. Released under the MIT license.

/**
 * pkValidatorFilePersistent validates an uploaded file, or
 * revalidates the existing file of the same browser-side name
 * uploaded on a previous submission by the same user in the case where 
 * no new file has been specified. 
 *
 * The file should come from the pkWidgetFormInputFilePersistent widget.
 *
 * Should behave like the parent class in all other respects.
 *
 * @see sfValidatorFile
 */
class pkValidatorFilePersistent extends sfValidatorFile
{

  /**
   * The input value must be an array potentially containing two
   * keys, newfile and persistid. newfile must contain an array of
   * the following subkeys, if it is present:
   *
   *  * tmp_name: The absolute temporary path to the newly uploaded file
   *  * name:     The original file name (optional)
   *  * type:     The file content type (optional)
   *  * error:    The error code (optional)
   *  * size:     The file size in bytes (optional)
   * 
   * The persistid key allows lookup of a previously uploaded file
   * when no new file has been submitted. 
   
   * A RARE BUT USEFUL CASE: if you need to prefill this cache before
   * invoking the form for the first time, you can instantiate this 
   * validator yourself:
   * 
   * $vfp = new pkValidatorFilePersistent();
   * $guid = $fvp->createGuid();
   * $vfp->clean(
   *   array(
   *     'newfile' => 
   *       array('tmp_name' => $myexistingfile), 
   *     'persistid' => $guid));
   *
   * Then set array('persistid' => $guid) as the default value
   * for the file widget. This logic is most easily encapsulated in
   * the configure() method of your form class.
   *
   * @see sfValidatorFile
   * @see sfValidatorBase
   */

  public function clean($value)
  {
    $user = sfContext::getInstance()->getUser();
    $persistid = $value['persistid'];
    $newFile = false;
    $persistentDir = $this->getPersistentDir();
    if (!self::validPersistId($persistid))
    {
      $persistid = false;
    }
    $cvalue = false;
    // Why do we tolerate the newfile fork being entirely absent?
    // Because with persistent file upload widgets, it's safe to
    // redirect a form submission to another action via the GET method
    // after validation... which is extremely useful if you want to
    // split something into an iframed initial upload action and
    // a non-iframed annotation action and you need to be able to
    // stuff the state of the form into a URL and do window.parent.location =.
    // As long as we tolerate the absence of the newfile button, we can
    // rebuild the submission from what's in 
    // getRequest()->getParameterHolder()->getAll(), and that is useful.
    if ((!isset($value['newfile']) || ($this->isEmpty($value['newfile']))))
    {
      if ($persistid !== false)
      {
        $filePath = "$persistentDir/$persistid.file";
        $data = false;
        if (file_exists($filePath))
        {
          $dataPath = "$persistentDir/$persistid.data";
          // Don't let them expire
          touch($filePath);
          touch($dataPath);
          $data = file_get_contents($dataPath);
          if (strlen($data))
          {
            $data = unserialize($data);
          }
        }
        if ($data)
        {
          $cvalue = $data;
        }
      }
    }
    else
    {
      $newFile = true;
      $cvalue = $value['newfile'];
    }
    // This will throw an exception if there is a validation error.
    // That's a good thing: we don't want to save it for reuse
    // in that situation.
    try
    {
      $result = parent::clean($cvalue);
    } catch (Exception $e)
    {
      // If there is a validation error stop keeping this
      // file around and don't display the reassuring
      // "you don't have to upload again" message side by side
      // with the validation error.
      if ($persistid !== false)
      {
        $infoPath = "$persistentDir/$persistid.data";
        $filePath = "$persistentDir/$persistid.file";
        @unlink($infoPath);
        @unlink($filePath);
      }
      throw $e;
    }
    if ($newFile)
    {
      self::removeOldFiles($this->getPersistentDir());
      if ($persistid !== false)
      {
        $filePath = "$persistentDir/$persistid.file";
        copy($cvalue['tmp_name'], $filePath);
        $data = $cvalue;
        $data['tmp_name'] = $filePath;
        file_put_contents("$persistentDir/$persistid.data", serialize($data));
      }
    }
    return $result;
  }

  static protected function getPersistentDir()
  {
    $dataDir = sfConfig::get('sf_data_dir');
    $persistentDir = "$dataDir/persistent-uploads";
    if (!file_exists($persistentDir))
    {
      if (!mkdir($persistentDir))
      {
        throw new Exception("Unable to create $persistentDir check permissions of parent directory or create this directory manually and make sure it is writable by the web server");
      }
    }
    return $persistentDir;
  }

  static public function createGuid()
  {
    $guid = "";
    for ($i = 0; ($i < 8); $i++) {
      $guid .= sprintf("%02x", mt_rand(0, 255));
    }
    return $guid;
  }

  static public function removeOldFiles($dir)
  {
    // Age off any stale uploads in the cache
    // (TODO: for performance, do this one time in a hundred or similar,
    // it's simple to do that probabilistically).
    $files = glob("$dir/*");
    $now = time();
    foreach ($files as $file)
    {
      if ($now - filemtime($file) > 
        sfConfig::get('sf_persistent_upload_lifetime', 60) * 60)
      {
        unlink($file); 
      }
    }
  }

  static public function getPreviewDir()
  {
    return sfConfig::get('sf_web_dir') . "/uploaded-image-preview";

  }

  static public function previewAvailable($value)
  {
    if (isset($value['persistid']))
    {
      $persistid = $value['persistid'];
      $info = self::getFileInfo($persistid);
      return !!$info['tmp_name'];
    }
    return false;
  }
  static public function getFileInfo($persistid)
  {
    if (!self::validPersistId($persistid))
    {
      // Roll our eyes at the hackers
      return false;
    }
    $persistentDir = self::getPersistentDir();
    $infoPath = "$persistentDir/$persistid.data";
    if (file_exists($infoPath))
    {
      return unserialize(file_get_contents($infoPath));
    }
    else
    {
      return false;
    }
  }

  static public function validPersistId($persistid)
  {
    return preg_match("/^[a-fA-F0-9]+$/", $persistid);
  }
}
