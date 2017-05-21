<?php

function error_exit($e)
{
  header('Content-Type: text/plain; charset=UTF-8', true, 500);
  print $e;
  exit;
}

?>
