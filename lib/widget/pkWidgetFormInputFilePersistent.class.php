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
    $this->addOption('existing-html', false);
    $this->addOption('image-preview', null);
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
    if (isset($value['persistid']) && strlen($value['persistid']))
    {
      $persistid = $value['persistid'];
      $info = pkValidatorFilePersistent::getFileInfo($persistid);
      if ($info)
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
        $width = $imagePreview['width'];
        $height = $imagePreview['height'];
        if ($height === false)
        {
          list($iwidth, $iheight) = getimagesize($info['tmp_name']);
          if ($iheight && $iwidth)
          {
            // Lack of rounding produced filenames with extra decimal points
            // which broke the previewer
            $height = floor($width * ($iheight / $iwidth));
          }
        }
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
            $width,
            $height);
        }
        $result .= "<img src='$url' />"; 
      }
      $result .= $this->getOption('existing-html');
    }
    return $result .
      $this->renderTag('input',
        array_merge(
          array(
            'type' => $this->getOption('type'),
            'name' => $name . '[newfile]'),
          $attributes)) .
      $this->renderTag('input',
        array(
          'type' => 'hidden',
          'name' => $name . '[persistid]',
          'value' => $persistid));
  }

}
