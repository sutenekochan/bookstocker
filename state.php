<?php

require_once(__DIR__ . '/setting.ini.php');
require_once(__DIR__ . '/lib/error.php');
require_once(__DIR__ . '/lib/function.php');
require_once(__DIR__ . '/lib/parsearg.php');
require_once(__DIR__ . '/lib/bookstockerdb.php');

$messages = [];

$db = BookStockerDB::getInstance();
if($db === NULL)
{
  error_exit($db->get_message());
}
$db->init(DB_DSN, DB_USERNAME, DB_PASSWORD);

if(PHP_SAPI == 'cli') {
  $parsedArgv = requestParser::createArgArrayFromArgv($argv);
  $rp = new requestParser([], $parsedArgv);
} else {
  $rp = new requestParser($_GET, $_POST);
}
$arg = $rp->getAllArg();
array_push($messages, $rp->getErrorMessagesAndClear);

require_once(__DIR__. '/lib/header.php');


// ---------- 追加・削除の処理 ---------- 
if(isset($arg["action"]))
{
  if($arg["action"] == "addState" && isset($arg["newState"]))
  {
    $ret1 = $db->addState($arg["newState"]);
    if($ret1 === FALSE)
    {
      array_push($messages, "追加に失敗しました");
      array_push($messages, $db->getErrorMessagesAndClear());
    }
    else
    {
      array_push($messages, "項目を追加しました");
    }
  }

  else if ($arg["action"] == "delState" && isset($arg["targetState"]))
  {
    $ret2 = $db->deleteState($arg["targetState"]);
    if($ret2 === FALSE)
    {
      array_push($messages, "削除に失敗しました");
      array_push($messages, $db->getErrorMessagesAndClear());
    }
    else
    {
      array_push($messages, "項目を削除しました");
    }
  }
}


// ---------- メッセージがある場合のみメッセージ表示 ---------- 
printMessages($messages);


// ---------- 項目一覧 ----------
$stateList = $db->getStateList();
$stateCount = count($stateList);
?>

<table border=1>
 <tr>
  <th style="width: 3em">ID
  <th>場所
  <th>&nbsp;
 </tr>

 <tr>
  <form method="POST" action="state.php">
  <td>新規
  <input type="hidden" name="action" value="addState">
  <td><input type="text" name="newState" size="40" value="" >
  <td><input type="submit" value="追加">
  </form>
 </tr>

<?php foreach ($stateList as $state) { ?>
 <tr>
  <td><?= htmlspecialchars($state["id"]); ?>
  <td><?= htmlspecialchars($state["state"]); ?>
  <?php if($stateCount >= 2) { ?>
  <td>
      <form method="POST" action="state.php">
      <input type="hidden" name="action" value="delState">
      <input type="hidden" name="targetState" value="<?= htmlspecialchars($state["id"]); ?>">
      <input type="submit" value="削除"></form>
 <?php } ?>
 </tr>
<?php } ?>

</table>

<?php
require_once(dirname(__FILE__) . '/lib/footer.php');
?>
