<?php

// Copyright 2009 P'unk Ave, LLC. Released under the MIT license.

/**
 * pkWidgetFormInputFilePersistent represents an upload HTML input tag
 * that doesn't lose its contents when the form is redisplayed due to 
 * a validation error in an unrelated field. Instead, the previously
 * submitted and successfully validated file is kept in a cache
 * managed on behalf of each user, and automatically reused if the
 * user doesn't choose to upload a new file but rather simply corrects
 * other fields and resubmits.
 */
class pkWidgetFormInputFilePersistent extends sfWidgetForm
{
  /**
   * @param array $options     An array of options
   * @param array $attributes  An array of default HTML attributes
   *
   * @see sfWidgetFormInput
   *
   * In reality builds an array of two controls using the [] form field
   * name syntax
   */
  protected function configure($options = array(), $attributes = array())
  {
    parent::configure($options, $attributes);

    $this->addOption('type', 'file');
    $this->addOption('iframe', null);
    $this->addOption('progress', null);
    $this->addOption('iframe-content', null);
    $this->addOption('existing-html', false);
    $this->addOption('image-preview', null);
    $this->addOption('persistid', null);
    $this->setOption('needs_multipart', true);
  }

  /**
   * @param  string $name        The element name
   * @param  string $value       The value displayed in this widget
   *                             (i.e. the browser-side filename submitted
   *                             on a previous partially successful
   *                             validation of this form)
   * @param  array  $attributes  An array of HTML attributes to be merged with the default HTML attributes
   * @param  array  $errors      An array of errors for the field
   *
   * @return string An HTML tag string
   *
   * @see sfWidgetForm
   */

  public function render($name, $value = null, $attributes = array(), $errors = array())
  {
    $exists = false;
    if ($this->hasOption('persistid'))
    {
      $persistid = $this->getOption('persistid');
    }
    elseif (isset($value['persistid']) && strlen($value['persistid']))
    {
      $persistid = $value['persistid'];
    }
    if (isset($persistid))
    {
      $info = pkValidatorFilePersistent::getFileInfo($persistid);
      if (isset($info['tmp_name']))
      {
        $exists = true;
      }
    }
    else
    {
      // One implementation, not two (to inevitably drift apart)
      $persistid = pkValidatorFilePersistent::createGuid();
    }
    $result = '';
    if ($exists)
    {
      if ($this->hasOption('image-preview'))
      {
        // While we're here age off stale previews
        $subdir = sfConfig::get('sf_persistent_upload_preview_dir', "/uploaded-image-preview");
        $parentdir = sfConfig::get('sf_web_dir');
        $dir = "$parentdir$subdir";
        pkValidatorFilePersistent::removeOldFiles($dir);
        $imagePreview = $this->getOption('image-preview');
        $width = $imagePreview['width'] + 0;
        $height = $imagePreview['height'] + 0;
        $resizeType = $imagePreview['resizeType'];
        if (!in_array($resizeType, array('c', 's')))
        {
          $resizeType = 'c';
        }
        $imagename = "$persistid.$width.$height.$resizeType.jpg";
        $url = "$subdir/$imagename";
        $output = "$dir/$imagename";
        if (!file_exists($output))
        {
          if ($imagePreview['resizeType'] === 'c')
          {
            $method = 'cropOriginal';
          }
          else
          {
            $method = 'scaleToFit';
          }
          if (!is_dir($dir))
          {
            // You may have to precreate this folder and give
            // it permissions that allow your web server full access
            mkdir($dir);
          }
          pkImageConverter::$method(
            $info['tmp_name'], 
            $output,
            $imagePreview['width'],
            $imagePreview['height']);
        }
        if ($this->getOption('iframe-content'))
        {
          // This is not security related, it just forces a refresh
          $salt = time();
          $salted = "$url?$salt";
          $result .= "<script>pkPersistentFileUploadUpdatePreview(json_encode($persistid), json_encode($salted));</script>";
        }
        else
        {
          $result .= "<img id='pk-persistent-upload-preview-$persistid' src='" . json_encode($url) . " />"; 
        }
      }
      $result .= $this->getOption('existing-html');
    }
    else
    {
      if ($this->hasOption('image-preview') && $this->hasOption('iframe'))
      {
        $result .= "<img id='pk-persistent-upload-preview-$persistid' style='display: none' />"; 
      }
    }
    if ($this->getOption('iframe'))
    {
      $iframe = $this->getOption('iframe');
      if (isset($iframe['width']))
      {
        $width = $iframe['width'];
      }
      else
      {
        $width = 400;
      }
      if (isset($iframe['height']))
      {
        $height = $iframe['height'];
      }
      else
      {
        $height = 50;
      }
      $result .= "<iframe class='pk-persistent-upload-iframe' id='pk-persistent-upload-iframe-$persistid' width='$width' height='$height' src='" . url_for("pkPersistentFileUpload/iframe?persistid=$persistid") . "'></iframe>";
      // We continue to build the result string as the fallback for a
      // device that doesn't do iframes
      $info = pkValidatorFilePersistent::getFileInfo($persistid);
      if ($info === false)
      {
        // That's OK, it's just the first pass
        $info = array();
      }
      // We need these so we can render it correctly from the
      // action in the iframe
      $info['options'] = $this->getOptions();
      $info['attributes'] = $this->getAttributes();
      pkValidatorFilePersistent::setFileInfo($persistid, $info);
      if ($this->hasOption('progress'))
      {
        $result .= "<div class='pk-persistent-upload-progress' id='pk-persistent-upload-progress-$persistid'>";
        {
          $result .= $this->getOption('progress');
        }
        $result .= "</div>";
      }
    }
    else
    {
      $result .=
        $this->renderTag('input',
          array_merge(
            array(
              'type' => $this->getOption('type'),
              'name' => $name . '[newfile]'),
            $attributes));
      $result .= 
        $this->renderTag('input',
          array(
            'type' => 'hidden',
            'name' => $name . '[persistid]',
            'value' => $persistid));
    }
    return $result;
  }
}
