<?php

require_once(dirname(__FILE__) . '/setting.ini.php');
require_once(dirname(__FILE__) . '/lib/error.php');
require_once(dirname(__FILE__) . '/lib/bookstockerdb.php');
require_once(dirname(__FILE__) . '/lib/amazonapi.php');
require_once(dirname(__FILE__) . '/lib/itemlist.php');

$db = BookStockerDB::getInstance();
if($db === NULL)
{
  error_exit($db->get_message());
}

$db->init(DB_DSN, DB_USERNAME, DB_PASSWORD);

$ama = new amazonApi(AWS_ACCESSKEY, AWS_SECRETKEY, AMAZON_ASSOCIATE_ID, CACHE_DIR);



require_once(dirname(__FILE__) . '/lib/header.php');


$message = "";


// ---------- 今後の処理のための変数をセット(1) 各種情報変更前にセットする情報 ---------- 
$selectedItemId = "";
if(isset($_REQUEST["itemid"]) && is_string($_REQUEST["itemid"]))
{
  $selectedItemId = $_REQUEST["itemid"];
}


$selectedPlace = 0;
if(isset($_REQUEST["place"]) && is_numeric($_REQUEST["place"]))
{
  $selectedPlace = $_REQUEST["place"];
}


$selectedState = 0;
if(isset($_REQUEST["state"]) && is_numeric($_REQUEST["state"]))
{
  $selectedState = $_REQUEST["state"];
}


$selectedTitle = "";
if(isset($_REQUEST["title"]) && is_string($_REQUEST["title"]))
{
  $selectedTitle = $_REQUEST["title"];
}


$selectedAuthor = "";
if(isset($_REQUEST["author"]) && is_string($_REQUEST["author"]))
{
  $selectedAuthor = $_REQUEST["author"];
}


$selectedPublisher = "";
if(isset($_REQUEST["publisher"]) && is_string($_REQUEST["publisher"]))
{
  $selectedPublisher = $_REQUEST["publisher"];
}


$selectedMemo = "";
if(isset($_REQUEST["memo"]) && is_string($_REQUEST["memo"]))
{
  $selectedMemo = $_REQUEST["memo"];
}


$placeList = $db->getPlaceList();
$stateList = $db->getStateList();


if(isset($_REQUEST["act"]) && $_REQUEST["act"] == "search")
{
  $itemList = $db->searchItem($selectedItemId, $selectedPlace, $selectedState, $selectedTitle, $selectedAuthor, $selectedPublisher, $selectedMemo);
  $itemCount = count($itemList);
  if($itemCount == 0)
  {
    $message = "検索条件にあう項目が見つかりませんでした";
  }
  else if ($itemCount > ITEMS_PER_PAGE)
  {
    $message = $itemCount . " 件の項目が見つかりました。1ページに表示できるサイズを超えたため、最初の" . ITEMS_PER_PAGE . "件を表示します";
  }
  else
  {
    $message = $itemCount . " 件の項目が見つかりました";
  }
}
else
{
  $itemList = [];
}


// ---------- メッセージがある場合のみメッセージ表示 ---------- 
printMessage($message);


// ---------- 検索フォーム表示 ----------
?>

<br>
<form method="POST" action="search.php">
<input type="hidden" name="act" value="search">

<table border=0>

<tr><td>商品コード <td><input type="text" name="itemid"     size="40" value="<?= $selectedItemId ?>">

<tr><td>保管場所
<td><select name="place">
 <option value="0">指定しない</option>
 <?php foreach ($placeList as $place) { ?>
 <option value="<?= htmlspecialchars($place['id']); ?>"<?php if($place['id'] == $selectedPlace) { ?> selected<?php } ?>><?= htmlspecialchars($place["place"]); ?></option>
 <?php } ?>
</select>
<br>

<tr><td>未読既読状態
<td><select name="state">
 <option value="0">指定しない</option>
 <?php foreach ($stateList as $state) { ?>
 <option value="<?= htmlspecialchars($state['id']); ?>"<?php if($state['id'] == $selectedState) { ?> selected<?php } ?>><?= htmlspecialchars($state["state"]); ?></option>
 <?php } ?>
</select>

<tr><td>タイトル   <td><input type="text" name="title"      size="40" value="<?= $selectedTitle ?>">
<tr><td>著者       <td><input type="text" name="author"     size="40" value="<?= $selectedAuthor ?>">
<tr><td>出版社     <td><input type="text" name="publisher"  size="40" value="<?= $selectedPublisher ?>">
<tr><td>メモ       <td><input type="text" name="memo"       size="40" value="<?= $selectedMemo ?>">

<tr><td>&nbsp;     <td><input type="submit" value="この条件で検索する">

</table>


</form>

<br>
<hr>

<?php
// ---------- アイテム一覧 ---------- 
printItemList($ama, $db, $itemList, 1, ITEMS_PER_PAGE, $placeList, $stateList);
?>


<?php
require_once(dirname(__FILE__) . '/lib/footer.php');
?>
