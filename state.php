<?php

require_once(dirname(__FILE__) . '/setting.ini.php');
require_once(dirname(__FILE__) . '/lib/error.php');
require_once(dirname(__FILE__) . '/lib/bookstockerdb.php');

$db = BookStockerDB::getInstance();
if($db === NULL)
{
  error_exit($db->get_message());
}

$db->init(DB_DSN, DB_USERNAME, DB_PASSWORD);




require_once(dirname(__FILE__) . '/lib/header.php');


$message = "";


// ---------- 追加・削除の処理 ---------- 
if(isset($_REQUEST["act"]))
{
  if($_REQUEST["act"] == "add" && isset($_REQUEST["state"]))
  {
    $ret1 = $db->addState($_REQUEST["state"]);
    if($ret1 === FALSE)
    {
      $message = "追加に失敗しました (" . $db->getLastError() . ")";
    }
    else
    {
      $message = "項目を追加しました";
    }
  }

  else if ($_REQUEST["act"] == "del" && isset($_REQUEST["id"]))
  {
    $ret2 = $db->deleteState($_REQUEST["id"]);
    if($ret2 === FALSE)
    {
      $message = "削除に失敗しました (" . $db->getLastError() . ")";
    }
    else
    {
      $message = "項目を削除しました";
    }
  }
}



?>


<?php
// ---------- メッセージがある場合のみメッセージ表示 ---------- 
  if($message !== "") {
?>
<textarea class="messagetext"><?= htmlspecialchars($message) ?></textarea>
<br>
<br>
<?php } ?>


<!-- ---------- 項目一覧 ---------- -->
<?php
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
  <td>新規
  <form method="POST" action="state.php">
  <input type="hidden" name="act" value="add">
  <td><input type="text" name="state" size="40" value="" >
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
      <input type="hidden" name="act" value="del">
      <input type="hidden" name="id" value="<?= htmlspecialchars($state["id"]); ?>">
      <input type="submit" value="削除"></form>
 <?php } ?>
 </tr>
<?php } ?>

</table>


<?php
require_once(dirname(__FILE__) . '/lib/footer.php');
?>
