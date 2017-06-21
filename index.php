<?php

require_once(__DIR__ . '/setting.ini.php');
require_once(__DIR__ . '/lib/error.php');
require_once(__DIR__ . '/lib/function.php');
require_once(__DIR__ . '/lib/parsearg.php');
require_once(__DIR__ . '/lib/bookstockerdb.php');
require_once(__DIR__ . '/lib/amazonapi.php');
require_once(__DIR__ . '/lib/itemlist.php');

$message = "";

$db = BookStockerDB::getInstance();
if($db === NULL)
{
  error_exit($db->get_message());
}
$db->init(DB_DSN, DB_USERNAME, DB_PASSWORD);

$ama = new amazonApi(AWS_ACCESSKEY, AWS_SECRETKEY, AMAZON_ASSOCIATE_ID, CACHE_DIR);

if(PHP_SAPI == 'cli') {
  $parsedArgv = requestParser::createArgArrayFromArgv($argv);
  $rp = new requestParser([], $parsedArgv);
} else {
  $rp = new requestParser($_GET, $_POST);
}
$arg = $rp->getAllArg();

$rpMessage = $rp->getErrorMessage();
foreach($rpMessage as $i)
{
  $message .= "$i\n";
}

require_once(__DIR__. '/lib/header.php');


// ---------- 今後の処理のための変数をセット(1) 各種情報変更前にセットする情報 ---------- 
$selectedPlace = 0;
if(isset($arg["place"]))
{
  $selectedPlace = $arg["place"][0];
}

$selectedState = 0;
if(isset($arg["state"]))
{
  $selectedState = $arg["state"][0];
}

$placeList = $db->getPlaceList();
$stateList = $db->getStateList();


// ---------- 追加処理 ---------- 
if(isset($arg["action"]))
{
  if($arg["action"] == "addItem" && isset($arg["newPlace"]) && isset($arg["newState"]))
  {

    // titleでの追加 (itemidよりこちらが優先)  AmazonのURLがあっても無視される
    if(isset($arg["newTitle"]) && $arg["newTitle"] != "")
    {
      $ret = $db->addItem(BookStockerDB::DataSource_UserDefined , $arg["newItemCode"], $arg["newTitle"], $arg["newAuthor"], $arg["newPublisher"], $arg["newPlace"], $arg["newState"]);
      if($ret === FALSE)
      {
        $message = "追加に失敗しました (db) \n" . $db->getLastError();
      }
      else
      {
        $message = "項目を追加しました";
      }
    }

    // itemidでの追加
    else if(isset($arg["newItemCode"]) && $arg["newItemCode"] != "" && (is_numeric($arg["newItemCode"]) || is_string($arg["newItemCode"])))
    {
      $itemid = $arg["newItemCode"];

      // 商品コード欄に入力されたものが http:// あるいは https:// で始まる場合、Amazon の URL とみなして変換する
      if(strstr($itemid, "http://") == $itemid || strstr($itemid, "https://") == $itemid)
      {
        $itemid = AmazonApi::getAsinFromUrl($itemid);
      }

      if($itemid === NULL)
      {
        $message = "追加に失敗しました\n必要なパラメータがセットされていないか、AmazonのURLからパラメータを推測できません";
      }
      else
      {
        // $itemid が13桁で(978|979)で始まる数値の場合、13桁ISBNとみなし、10桁に変換
        if(is_numeric($itemid) && strlen($itemid) == 13 && (strstr($itemid, "978") == $itemid || strstr($itemid, "979") == $itemid))
        {
          $itemid = amazonApi::isbn13toIsbn10($itemid);
        }

        // Amazonから情報を得てキャッシュに保存
        $newItem = $ama->searchByAsin($itemid);

        if($newItem === NULL)
        {
          $message = "追加に失敗しました。時間をおいて試してください (Amazonアクセスエラー[" . $itemid . "])\n" . $ama->getLastError();
        }
        else
        {
          $ret = $db->addItem(BookStockerDB::DataSource_Amazon, $itemid, $newItem->getTitle(), $newItem->getAuthor(), $newItem->getPublisher(), $arg["newPlace"], $arg["newState"]);
          if($ret === FALSE)
          {
            $message = "追加に失敗しました (db)\n" . $db->getLastError();
          }
          else
          {
            $message = "項目を追加しました";
          }
        }
      }
    }
  }


// ---------- 削除処理 ---------- 
  else if ($arg["action"] == "delItem" && isset($arg["targetItem"]))
  {
    $ret = $db->deleteItem($arg["targetItem"]);
    if($ret === FALSE)
    {
      $message = "削除に失敗しました\n" . $db->getLastError();
    }
    else
    {
      $message = "項目を削除しました";
    }
  }


// ---------- 変更処理(place) ---------- 
  else if($arg["action"] == "modifyItem" && isset($arg["targetItem"]))
  {
    if(isset($arg["newPlace"]))
    {
      $ret = $db->modifyItemPlace($arg["targetItem"], $arg["newPlace"]);
      if($ret === FALSE)
      {
        $message = "保管場所の変更に失敗しました\n" . $db->getLastError();
      }
      else
      {
        $message = "保管場所を変更しました";
      }
    }


// ---------- 変更処理(state) ---------- 
    if(isset($arg["newState"]))
    {
      $ret = $db->modifyItemState($arg["targetItem"], $arg["newState"]);
     if($ret === FALSE)
      {
        $message = "ステータスの変更に失敗しました\n" . $db->getLastError();
      }
      else
      {
        $message = "ステータスを変更しました";
      }
    }


// ---------- 変更処理(memo) ---------- 
    if(isset($arg["newMemo"]))
    {
      $ret = $db->modifyItemMemo($arg["targetItem"], $arg["newMemo"]);
      if($ret === FALSE)
      {
        $message = "メモの変更に失敗しました\n" . $db->getLastError();
      }
      else
      {
        $message = "メモを変更しました";
      }
    }
  }
}


// ---------- 今後の処理のための変数をセット(2) 各種情報変更後にセットする情報 ---------- 
$itemList = $db->getItemList($selectedPlace, $selectedState);

$itemCount = count($itemList);
$pageCount = floor(($itemCount + ITEMS_PER_PAGE - 1) / ITEMS_PER_PAGE);
if($pageCount == 0) { $pageCount = 1; }

if(isset($arg["p"]) && is_numeric($arg["p"]))
{
  $currentPage = $arg["p"];
}
else
{
  $currentPage = 1;
}


// ---------- メッセージがある場合のみメッセージ表示 ---------- 
printMessage($message);


// ---------- 新規登録 ---------- -->
?>

<br>
 <form method="POST" action="index.php">

 <input type="hidden" name="action" value="addItem">
 商品コード&nbsp;
 <input type="text" name="newItemCode" size="40" value="" placeholder="ISBN、ASIN、AmazonのURL(短縮してないもの)">&nbsp;

 <input type="submit" value="新規登録">

<br>

<script><!--
  var newItemDisplayState = "none";
  function toggleNewItemDisplay()
  {
    if(newItemDisplayState == "block")
    {
      document.getElementById("newItemDetail").style.display="none";
      newItemDisplayState = "none";
    } else {
      document.getElementById("newItemDetail").style.display="block";
      newItemDisplayState = "block";
    }
  }

-->
</script>


<a href="#" onclick="toggleNewItemDisplay();">▲▼</a>

<br>
<div id="newItemDetail" style="display: none">

<table border=0>

<tr><td>保管場所
<td><select name="newPlace">
 <?php foreach ($placeList as $place) { ?>
 <option value="<?= htmlspecialchars($place['id']); ?>"><?= htmlspecialchars($place["place"]); ?></option>
 <?php } ?>
</select>
<br>

<tr><td>未読既読状態
<td><select name="newState">
 <?php foreach ($stateList as $state) { ?>
 <option value="<?= htmlspecialchars($state['id']); ?>"><?= htmlspecialchars($state["state"]); ?></option>
 <?php } ?>
</select>
<tr><td colspan=2>&nbsp;
<tr><td colspan=2>Amazonにないアイテムの場合<br>

<tr><td>タイトル   <td><input type="text" name="newTitle"     size="40" value="">
<tr><td>著者       <td><input type="text" name="newAuthor"    size="40" value="">
<tr><td>出版社     <td><input type="text" name="newPublisher" size="40" value="">

</table>

</div>
</form>

<br>
<hr>


<?php
// ---------- フィルタ ---------- 
?>
<span class="subTitleText">フィルタ：</span>&nbsp;

<form class="filterFormArea" method="GET" action="index.php">

保管場所
<select name="place" onchange="this.form.submit()">
 <option value="0">指定しない</option>
 <?php foreach ($placeList as $place) { ?>
 <option value="<?= htmlspecialchars($place['id']); ?>"<?php if($place['id'] == $selectedPlace) { ?> selected<?php } ?>><?= htmlspecialchars($place["place"]); ?></option>
 <?php } ?>
</select>
&nbsp;&nbsp;

未読既読状態
<select name="state" onchange="this.form.submit()">
 <option value="0">指定しない</option>
 <?php foreach ($stateList as $state) { ?>
 <option value="<?= htmlspecialchars($state['id']); ?>"<?php if($state['id'] == $selectedState) { ?> selected<?php } ?>><?= htmlspecialchars($state["state"]); ?></option>
 <?php } ?>
</select>

</form>
<hr>

<?php
// ---------- アイテム一覧 ---------- 
printItemList($ama, $db, $itemList, ($currentPage - 1) * ITEMS_PER_PAGE + 1, ITEMS_PER_PAGE, $placeList, $stateList);
?>

<span class="subTitleText">ページ：<?php printItemPageLink("index.php", $currentPage, $pageCount, $selectedPlace, $selectedState); ?></span><br>

<?php
// ---------- フッタ ---------- 
require_once(dirname(__FILE__) . '/lib/footer.php');
?>
