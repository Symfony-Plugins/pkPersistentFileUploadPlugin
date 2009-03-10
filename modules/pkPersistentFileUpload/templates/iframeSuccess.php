<form method="POST" enctype="multipart/form-data" action="<?php echo url_for("pkPersistentFileUpload/iframe?persistid=<?php echo $persistid ?>") ?>" onSubmit="pkPersistentFileUploadShowProgress("<?php echo $persistid ?>", true); return true">
<?php // TODO: communicate the image preview upstream to the parent form ?>
<?php echo $form['file']->render() ?>
</form>
<script>
function pkPersistentFileUploadShowProgress(persistid, state)
{
  var iframe = window.parent.getElementById("pk-persistent-upload-iframe-" + persistid);
  var progress = window.parent.getElementById("pk-persistent-upload-progress-" + persistid);
  if (progress)
  {
    if (state)
    {
      iframe.style.visibility = 'hidden';
      progress.style.visibility = 'visible';
    }
    else
    {
      iframe.style.visibility = 'visible';
      progress.style.visibility = 'hidden';
    }
  }
}

function pkPersistentFileUploadUpdatePreview(persistid, url)
{
  var preview = window.parent.getElementById("pk-persistent-upload-progress-" + persistid);
  if (preview)
  {
    preview.src = url;
    preview.style.display = 'block';
  }
}

pkPersistentFileUploadShowProgress("<?php echo $persistid ?>", false);
</script>
