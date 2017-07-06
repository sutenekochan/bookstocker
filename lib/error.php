<?php

// ---------- エラーを表示して終わる ----------
function error_exit($e)
{
  header('Content-Type: text/plain; charset=UTF-8', true, 500);
  print $e;
  exit;
}


// ---------- Warningをtextareaで表示 ----------
function printMessages($messages)
{
  if(count($messages) > 0)
  {
?>
<div class="messagetext">
<?php
    foreach($messages as $i)
    {
      $i = htmlspecialchars($i);
      //$i = str_replace("\n", "<br>\n", $i);
?><?= $i ?><?php
    }
?>
</div>
<br>
<br>
<?php
  }
}


?>
