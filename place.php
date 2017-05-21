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
  if($_REQUEST["act"] == "add" && isset($_REQUEST["place"]))
  {
    $ret1 = $db->addPlace($_REQUEST["place"]);
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
    $ret2 = $db->deletePlace($_REQUEST["id"]);
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



// ---------- メッセージがある場合のみメッセージ表示 ---------- 
printMessage($message);


// ---------- 項目一覧 ----------
  $placeList = $db->getPlaceList();
  $placeCount = count($placeList);
?>

<table border=1>
 <tr>
  <th style="width: 3em">ID
  <th>場所
  <th>&nbsp;
 </tr>

 <tr>
  <td>新規
  <form method="POST" action="place.php">
  <input type="hidden" name="act" value="add">
  <td><input type="text" name="place" size="40" value="" >
  <td><input type="submit" value="追加">
  </form>
 </tr>

<?php foreach ($placeList as $place) { ?>
 <tr>
  <td><?= htmlspecialchars($place["id"]); ?>
  <td><?= htmlspecialchars($place["place"]); ?>
  <?php if($placeCount >= 2) { ?>
  <td>
      <form method="POST" action="place.php">
      <input type="hidden" name="act" value="del">
      <input type="hidden" name="id" value="<?= htmlspecialchars($place["id"]); ?>">
      <input type="submit" value="削除"></form>
 <?php } ?>
 </tr>
<?php } ?>

</table>


<?php
require_once(dirname(__FILE__) . '/lib/footer.php');
?>
