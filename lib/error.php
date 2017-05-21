<?php

// ---------- エラーを表示して終わる ----------
function error_exit($e)
{
  header('Content-Type: text/plain; charset=UTF-8', true, 500);
  print $e;
  exit;
}


// ---------- Warningをtextareaで表示 ----------
function printMessage($message)
{
  if($message !== "")
  {
    $message = htmlspecialchars($message);
    $message = str_replace("\n", "<br>\n", $message);
?>
<div class="messagetext"><?= $message ?></div>
<br>
<br>
<?php
  }


}


?>
