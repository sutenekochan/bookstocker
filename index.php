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
$messages += $rp->getErrorMessagesAndClear();

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

$selectedTag = [];
if(isset($arg["tag"]) && $arg["tag"] !== [0])
{
  $selectedTag = $arg["tag"];
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
$tagList   = $db->getTagList();


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
        array_push($messages, "追加に失敗しました (db)");
        $messages += $db->getErrorMessagesAndClear();
      }
      else
      {
        array_push($messages, "項目を追加しました");
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
        array_push($messages, "追加に失敗しました");
        array_push($messages, "必要なパラメータがセットされていないか、AmazonのURLからパラメータを推測できません");
      }
      else
      {
        // 文字列が ISBN で始まる場合はそれを取り去る
        if(stristr($itemid, "ISBN") == $itemid) 
        {
          $itemid = substr($itemid, 4);
        }

        // 文字列中のハイフンを取り去る
        $itemid = str_replace("-", "", $itemid);

        // $itemid が13桁で(978|979)で始まる数値の場合、13桁ISBNとみなし、10桁に変換
        if(is_numeric($itemid) && strlen($itemid) == 13 && (strstr($itemid, "978") == $itemid || strstr($itemid, "979") == $itemid))
        {
          $itemid = amazonApi::isbn13toIsbn10($itemid);
        }

        // 10桁ISBNのCheck Digitを再計算。副作用として、Check Digitに文字"x"が含まれていた場合、小文字が大文字に変換される
        if(strlen($itemid) == 10)
        {
          //$itemid = strtoupper($itemid);
          $itemid = amazonApi::calcIsbn10CheckDigit($itemid);
        } 

        // 補正した結果が10桁で無い場合や不正文字を含む場合、Amazonに行かずにエラーとする
        if(strlen($itemid) != 10 || containsHtmlSqlSpecialCharactors($itemid))
        {
          array_push($messages, "指定された値はASIN(Amazonの商品コード)として正しくないか、正しく変換できません[" . htmlspecialchars($itemid) . "]");
        }
        else
        {
          // Amazonから情報を得てキャッシュに保存
          $newItem = $ama->searchByAsin($itemid);

          if($newItem === NULL)
          {
            array_push($messages, "追加に失敗しました。時間をおいて試してください (Amazonアクセスエラー[" . htmlspecialchars($itemid) . "])");
            $messages += $ama->getErrorMessagesAndClear();
          }
          else
          {
            $ret = $db->addItem(BookStockerDB::DataSource_Amazon, $itemid, $newItem->getTitle(), $newItem->getAuthor(), $newItem->getPublisher(), $arg["newPlace"], $arg["newState"]);
            if($ret === FALSE)
            {
              array_push($messages, "追加に失敗しました (db)");
              $messages += $db->getErrorMessagesAndClear();
            }
            else
            {
              array_push($messages, "項目を追加しました");
            }
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
      array_push($messages, "削除に失敗しました");
      $messages += $db->getErrorMessagesAndClear();
    }
    else
    {
      array_push($messages, "項目を削除しました");
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
        array_push($messages, "保管場所の変更に失敗しました");
        $messages += $db->getErrorMessagesAndClear();
      }
      else
      {
        array_push($messages, "保管場所を変更しました");
      }
    }


// ---------- 変更処理(state) ---------- 
    if(isset($arg["newState"]))
    {
      $ret = $db->modifyItemState($arg["targetItem"], $arg["newState"]);
     if($ret === FALSE)
      {
        array_push($messages, "ステータスの変更に失敗しました");
        $messages += $db->getErrorMessagesAndClear();
      }
      else
      {
        array_push($messages, "ステータスを変更しました");
      }
    }


// ---------- 変更処理(memo) ---------- 
    if(isset($arg["deleteMemoFlag"]))
    {
      $ret = $db->modifyItemMemo($arg["targetItem"], NULL);
      if($ret === FALSE)
      {
        array_push($messages, "メモの削除に失敗しました");
        $messages += $db->getErrorMessagesAndClear();
      }
      else
      {
        array_push($messages, "メモを削除しました");
      }
    }

    if(isset($arg["newMemo"]))
    {
      $ret = $db->modifyItemMemo($arg["targetItem"], $arg["newMemo"]);
      if($ret === FALSE)
      {
        array_push($messages, "メモの変更に失敗しました");
        $messages += $db->getErrorMessagesAndClear();
      }
      else
      {
        array_push($messages, "メモを変更しました");
      }
    }

  }


// ---------- 変更処理(tag) ---------- 
  else if($arg["action"] == "addTagRef" && isset($arg["targetItem"]) && isset($arg["targetTag"]))
  {
    $ret = $db->addTagRef($arg["targetItem"], $arg["targetTag"]);
    if($ret === FALSE)
    {
      array_push($messages, "タグの追加に失敗しました");
      $messages += $db->getErrorMessagesAndClear();
    }
    else
    {
      array_push($messages, "タグを追加しました");
    }
  }

  else if($arg["action"] == "delTagRef" && isset($arg["targetItem"]) && isset($arg["targetTag"]))
  {
    $ret = $db->deleteTagRef($arg["targetItem"], $arg["targetTag"]);
   if($ret === FALSE)
    {
     array_push($messages, "タグの追加に失敗しました");
      $messages += $db->getErrorMessagesAndClear();
    }
    else
    {
      array_push($messages, "タグを追加しました");
    }
  }

}


// ---------- 今後の処理のための変数をセット(2) 各種情報変更後にセットする情報 ---------- 
$itemList = $db->searchItem($selectedPlace, $selectedState, $selectedTag, $selectedId, $selectedItemCode, $selectedTitle, $selectedAuthor, $selectedPublisher, $selectedMemo);

$itemCount = count($itemList);

if($selectedId !== [] || $selectedItemCode !== [] || $selectedPlace !== [] || $selectedState !== [] || $selectedTag !== [] ||
   $selectedTitle !== [] || $selectedAuthor !== [] || $selectedPublisher !== [] || $selectedMemo !== [])
{
  // 検索結果の場合にはメッセージを表示
  if($itemCount == 0)
  {
    array_push($messages, "検索条件にあう項目が見つかりませんでした");
  }
  else
  {
    array_push($messages, $itemCount . " 件の項目が見つかりました");
  }
}

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
printMessages($messages);


// ---------- 新規登録 ----------
?>

<br>

<div id="newItemDiv" style="display: none">

<form method="POST" action="index.php">

<input type="hidden" name="action" value="addItem">

<table border=0>

<tr><td>商品コード
    <td><input type="text" name="newItemCode" size="40" value="" placeholder="ISBN、ASIN、AmazonのURL(短縮してないもの)">
    <td><input type="submit" value="新規登録">
</tr>

<tr><td colspan=3>&nbsp;

<tr><td>保管場所
    <td colspan=2><select name="newPlace">
      <?php foreach ($placeList as $place) { ?>
        <option value="<?= htmlspecialchars($place['id']); ?>"><?= htmlspecialchars($place["place"]); ?></option>
      <?php } ?>
      </select>

<tr><td>未読既読状態
    <td colspan=2><select name="newState">
    <?php foreach ($stateList as $state) { ?>
      <option value="<?= htmlspecialchars($state['id']); ?>"><?= htmlspecialchars($state["state"]); ?></option>
    <?php } ?>
    </select>

<tr><td colspan=3>&nbsp;
<tr><td colspan=3>Amazonにないアイテムの場合<br>

<tr><td>タイトル   <td colspan=2><input type="text" name="newTitle"     size="40" value="">
<tr><td>著者       <td colspan=2><input type="text" name="newAuthor"    size="40" value="">
<tr><td>出版社     <td colspan=2><input type="text" name="newPublisher" size="40" value="">

</table>
</form>

<br>
<hr>
</div>


<?php
// ---------- 検索 ---------- 
?>

<div id="searchDiv" style="display: none">

<form method="GET" action="index.php">

<table border=0>

<tr><td>タイトル   <td><input type="text" name="title"      size="40" value="<?= implode(" ", $selectedTitle) ?>">
<tr><td>著者       <td><input type="text" name="author"     size="40" value="<?= implode(" ", $selectedAuthor) ?>">
<tr><td>出版社     <td><input type="text" name="publisher"  size="40" value="<?= implode(" ", $selectedPublisher) ?>">
<tr><td>メモ       <td><input type="text" name="memo"       size="40" value="<?= implode(" ", $selectedMemo) ?>">


<tr><td>DB内ID <td><input type="text" name="id" size="40" value="<?= implode(" ", $selectedId) ?>">

<tr><td>商品コード <td><input type="text" name="itemCode" size="40" value="<?= implode(" ", $selectedItemCode) ?>">

<tr><td>保管場所
<td><select name="place">
 <option value="0">指定しない</option>
 <?php foreach ($placeList as $place) { ?>
 <option value="<?= htmlspecialchars($place['id']); ?>"<?php if(count($selectedPlace) > 0 && $place['id'] == $selectedPlace[0]) { ?> selected<?php } ?>><?= htmlspecialchars($place["place"]); ?></option>
 <?php } ?>
</select>
<br>

<tr><td>未読既読状態
<td><select name="state">
 <option value="0">指定しない</option>
 <?php foreach ($stateList as $state) { ?>
 <option value="<?= htmlspecialchars($state['id']); ?>"<?php if(count($selectedState) > 0 && $state['id'] == $selectedState[0]) { ?> selected<?php } ?>><?= htmlspecialchars($state["state"]); ?></option>
 <?php } ?>
</select>

<tr><td>タグ
<td><select name="tag">
 <option value="0">指定しない</option>
 <?php foreach ($tagList as $tag) { ?>
 <option value="<?= htmlspecialchars($tag['id']); ?>"<?php if(count($selectedTag) > 0 && $tag['id'] == $selectedTag[0]) { ?> selected<?php } ?>><?= htmlspecialchars($tag["tag"]); ?></option>
 <?php } ?>
</select>


<tr><td>&nbsp;     <td><input type="submit" value="この条件で検索する">

</table>
</form>

<br>
<hr>
</div>


<?php
// ---------- ページナビゲーション ---------- 
?>
<span class="subTitleText"><?php printItemPageLink("index.php", $currentPage, $pageCount, $itemCount, $selectedPlace, $selectedState, $selectedTag, $selectedId, $selectedItemCode, $selectedTitle, $selectedAuthor, $selectedPublisher, $selectedMemo); ?></span><br>
<hr>

<?php
// ---------- アイテム一覧 ---------- 
printItemList($ama, $db, $itemList, ($currentPage - 1) * ITEMS_PER_PAGE + 1, ITEMS_PER_PAGE);
?>

<?php
// ---------- ページナビゲーション ---------- 
?>
<span class="subTitleText"><?php printItemPageLink("index.php", $currentPage, $pageCount, $itemCount, $selectedPlace, $selectedState, $selectedTag, $selectedId, $selectedItemCode, $selectedTitle, $selectedAuthor, $selectedPublisher, $selectedMemo); ?></span><br>

<?php
// ---------- フッタ ---------- 
require_once(dirname(__FILE__) . '/lib/footer.php');
?>
