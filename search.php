<?php

require_once(__DIR__ . '/setting.ini.php');
require_once(__DIR__ . '/lib/error.php');
require_once(__DIR__ . '/lib/function.php');
require_once(__DIR__ . '/lib/parsearg.php');
require_once(__DIR__ . '/lib/bookstockerdb.php');
require_once(__DIR__ . '/lib/amazonapi.php');
require_once(__DIR__ . '/lib/itemlist.php');

$messages = [];

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
array_push($messages, $rp->getErrorMessagesAndClear);

require_once(__DIR__. '/lib/header.php');


// ---------- 今後の処理のための変数をセット(1) 各種情報変更前にセットする情報 ---------- 
$selectedId = [];
if(isset($arg["id"]))
{
  $selectedId = $arg["id"];
}

$selectedItemCode = [];
if(isset($arg["itemCode"]))
{
  $selectedItemCode = $arg["itemCode"];
}

$selectedPlace = [];
if(isset($arg["place"]) && $arg["place"] !== [0])
{
  $selectedPlace = $arg["place"];
}

$selectedState = [];
if(isset($arg["state"]) && $arg["state"] !== [0])
{
  $selectedState = $arg["state"];
}

$selectedTitle = [];
if(isset($arg["title"]))
{
  $selectedTitle = $arg["title"];
}

$selectedAuthor = [];
if(isset($arg["author"]))
{
  $selectedAuthor = $arg["author"];
}

$selectedPublisher = [];
if(isset($arg["publisher"]))
{
  $selectedPublisher = $arg["publisher"];
}

$selectedMemo = [];
if(isset($arg["memo"]))
{
  $selectedMemo = $arg["memo"];
}


$placeList = $db->getPlaceList();
$stateList = $db->getStateList();


$isSearchResultPage = FALSE;  // 検索結果を表示する動作か否か
if($selectedId !== [] || $selectedItemCode !== [] || $selectedPlace !== [] || $selectedState !== [] ||
   $selectedTitle !== [] || $selectedAuthor !== [] || $selectedPublisher !== [] || $selectedMemo !== [])
{
  $itemList = $db->searchItem($selectedPlace, $selectedState, $selectedId, $selectedItemCode, $selectedTitle, $selectedAuthor, $selectedPublisher, $selectedMemo);
  $itemCount = count($itemList);
  if($itemCount == 0)
  {
    array_push($messages, "検索条件にあう項目が見つかりませんでした");
  }
  else if ($itemCount > ITEMS_PER_PAGE)
  {
    array_push($messages, $itemCount . " 件の項目が見つかりました。1ページに表示できるサイズを超えたため、最初の" . ITEMS_PER_PAGE . "件を表示します");
    $isSearchResultPage = TRUE;
  }
  else
  {
    array_push($messages, $itemCount . " 件の項目が見つかりました");
    $isSearchResultPage = TRUE;
  }
}
else
{
  $itemList = [];
}


if($isSearchResultPage)
{
  $searchFormPrintDefault = "none";  // 検索フォームを表示するか否か
} else {
  $searchFormPrintDefault = "block";
}

// ---------- メッセージがある場合のみメッセージ表示 ---------- 
printMessages($messages);


// ---------- 検索フォーム表示 ----------
?>

<script><!--
  var searchFormDisplayState = "<?= $searchFormPrintDefault ?>";
  function toggleSearchFormDisplay()
  {
    if(searchFormDisplayState == "block")
    {
      document.getElementById("searchForm").style.display="none";
      searchFormDisplayState = "none";
    } else {
      document.getElementById("searchForm").style.display="block";
      searchFormDisplayState = "block";
    }
  }

-->
</script>

<a href="#" onclick="toggleSearchFormDisplay();">▲▼</a>

<div id="searchForm" style="display: <?= $searchFormPrintDefault ?>">
<form method="GET" action="search.php">

<table border=0>

<tr><td>DB内ID <td><input type="text" name="id" size="40" value="<?= implode(" ", $selectedId) ?>">

<tr><td>商品コード <td><input type="text" name="itemCode" size="40" value="<?= implode(" ", $selectedItemCode) ?>">

<tr><td>保管場所
<td><select name="place">
 <option value="0">指定しない</option>
 <?php foreach ($placeList as $place) { ?>
 <option value="<?= htmlspecialchars($place['id']); ?>"<?php if($place['id'] == $selectedPlace[0]) { ?> selected<?php } ?>><?= htmlspecialchars($place["place"]); ?></option>
 <?php } ?>
</select>
<br>

<tr><td>未読既読状態
<td><select name="state">
 <option value="0">指定しない</option>
 <?php foreach ($stateList as $state) { ?>
 <option value="<?= htmlspecialchars($state['id']); ?>"<?php if($state['id'] == $selectedState[0]) { ?> selected<?php } ?>><?= htmlspecialchars($state["state"]); ?></option>
 <?php } ?>
</select>

<tr><td>タイトル   <td><input type="text" name="title"      size="40" value="<?= implode(" ", $selectedTitle) ?>">
<tr><td>著者       <td><input type="text" name="author"     size="40" value="<?= implode(" ", $selectedAuthor) ?>">
<tr><td>出版社     <td><input type="text" name="publisher"  size="40" value="<?= implode(" ", $selectedPublisher) ?>">
<tr><td>メモ       <td><input type="text" name="memo"       size="40" value="<?= implode(" ", $selectedMemo) ?>">

<tr><td>&nbsp;     <td><input type="submit" value="この条件で検索する">

</table>

</form>
</div>

<br>
<hr>

<?php
// ---------- アイテム一覧 ---------- 
printItemList($ama, $db, $itemList, 1, ITEMS_PER_PAGE, $placeList, $stateList);
?>


<?php
require_once(dirname(__FILE__) . '/lib/footer.php');
?>
