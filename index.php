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
  for($x=0; $x<count($selectedItemCode); $x++){
    $selectedItemCode[$x] = normalizeAsin($selectedItemCode[$x]);
  }
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

$addItemDefaultItemCode  = "";
$addItemDefaultTitle     = "";
$addItemDefaultAuthor    = "";
$addItemDefaultPublisher = "";


$placeList = $db->getPlaceList();
$stateList = $db->getStateList();
$tagList   = $db->getTagList();


// ---------- 追加処理 ---------- 
if(isset($arg["action"]))
{
  if($arg["action"] == "addItem" && isset($arg["newPlace"]) && isset($arg["newState"]))
  {
    $addItemSuccess = FALSE;

    // titleでの追加 (itemidよりこちらが優先)  AmazonのURLがあっても無視される
    if(isset($arg["newTitle"]) && $arg["newTitle"] != "")
    {
      $ret = $db->addItem(BookStockerDB::DataSource_UserDefined , $arg["newItemCode"], $arg["newTitle"], $arg["newAuthor"], $arg["newPublisher"], $arg["newPlace"], $arg["newState"]);
      if($ret === FALSE)
      {
        array_push($messages, "追加に失敗しました (db)");
        $messages += $db->getErrorMessagesAndClear();
        if(isset($arg["newItemCode"] )) { $addItemDefaultItemCode  = $arg["newItemCode"];  }
        if(isset($arg["newTitle"]    )) { $addItemDefaultTitle     = $arg["newTitle"];     }
        if(isset($arg["newAuthor"]   )) { $addItemDefaultAuthor    = $arg["newAuthor"];    }
        if(isset($arg["newPublisher"])) { $addItemDefaultPublisher = $arg["newPublisher"]; }
      }
      else
      {
        array_push($messages, "項目を追加しました");
        $addItemSuccess = TRUE;
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
        if(isset($arg["newItemCode"] )) { $addItemDefaultItemCode  = $arg["newItemCode"];  }
        if(isset($arg["newTitle"]    )) { $addItemDefaultTitle     = $arg["newTitle"];     }
        if(isset($arg["newAuthor"]   )) { $addItemDefaultAuthor    = $arg["newAuthor"];    }
        if(isset($arg["newPublisher"])) { $addItemDefaultPublisher = $arg["newPublisher"]; }
      }
      else
      {
        $itemid = normalizeAsin($itemid);

        // 補正した結果が10桁で無い場合や不正文字を含む場合、Amazonに行かずにエラーとする
        if(strlen($itemid) != 10 || containsHtmlSqlSpecialCharactors($itemid))
        {
          array_push($messages, "指定された値はASIN(Amazonの商品コード)として正しくないか、正しく変換できません[" . htmlspecialchars($itemid) . "]");
          if(isset($arg["newItemCode"] )) { $addItemDefaultItemCode  = $arg["newItemCode"];  }
          if(isset($arg["newTitle"]    )) { $addItemDefaultTitle     = $arg["newTitle"];     }
          if(isset($arg["newAuthor"]   )) { $addItemDefaultAuthor    = $arg["newAuthor"];    }
          if(isset($arg["newPublisher"])) { $addItemDefaultPublisher = $arg["newPublisher"]; }
        }
        else
        {
          // Amazonから情報を得てキャッシュに保存
          $newItem = $ama->searchByAsin($itemid);

          if($newItem === NULL)
          {
            array_push($messages, "追加に失敗しました。時間をおいて試してください (Amazonアクセスエラー[" . htmlspecialchars($itemid) . "])");
            $messages += $ama->getErrorMessagesAndClear();
            if(isset($arg["newItemCode"] )) { $addItemDefaultItemCode  = $arg["newItemCode"];  }
            if(isset($arg["newTitle"]    )) { $addItemDefaultTitle     = $arg["newTitle"];     }
            if(isset($arg["newAuthor"]   )) { $addItemDefaultAuthor    = $arg["newAuthor"];    }
            if(isset($arg["newPublisher"])) { $addItemDefaultPublisher = $arg["newPublisher"]; }
          }
          else
          {
            $ret = $db->addItem(BookStockerDB::DataSource_Amazon, $itemid, $newItem->getTitle(), $newItem->getAuthor(), $newItem->getPublisher(), $arg["newPlace"], $arg["newState"]);
            if($ret === FALSE)
            {
              array_push($messages, "追加に失敗しました (db)");
              $messages += $db->getErrorMessagesAndClear();
              if(isset($arg["newItemCode"] )) { $addItemDefaultItemCode  = $arg["newItemCode"];  }
              if(isset($arg["newTitle"]    )) { $addItemDefaultTitle     = $arg["newTitle"];     }
              if(isset($arg["newAuthor"]   )) { $addItemDefaultAuthor    = $arg["newAuthor"];    }
              if(isset($arg["newPublisher"])) { $addItemDefaultPublisher = $arg["newPublisher"]; }
            }
            else
            {
              // 画像キャッシュを保存
              $newItemAsin = $newItem->getAsin();
              $newItemImageUrl = $newItem->getMediumImageUrl();
              $newItemImageExt = substr($newItemImageUrl, strrpos($newItemImageUrl, "."));
              if(!(file_exists(BOOK_IMAGE_DIR . "/asin_" . $newItemAsin . $newItemImageExt)) && $newItemImageUrl !==  "")
              {
                $newItemFileName = BOOK_IMAGE_URL . "asin_" . $newItemAsin . $newItemImageExt;
                $newItemContents = file_get_contents($newItemImageUrl);
                $newItemFileHandle = fopen($newItemFileName, "wb");
                fwrite($newItemFileHandle, $newItemContents);
                fclose($newItemFileHandle);
              }

              array_push($messages, "項目を追加しました");
              $addItemSuccess = TRUE;
            }
          }
        }
      }
    } // if(isset($arg["newTitle"]) && $arg["newTitle"] != "")

    // 表紙画像アップロードの処理
    if($addItemSuccess && $_FILES['newBookImage']['error'] == UPLOAD_ERR_OK && $_FILES['newBookImage']['size'] > 0)
    {
      $newImageExt = "";
      if($_FILES['newBookImage']['type'] == "image/jpeg" || $_FILES['newBookImage']['type'] == "image/jpg") { $newImageExt = "jpg"; }
      else if($_FILES['newBookImage']['type'] == "image/png") { $newImageExt = "png"; }

      $newItemId = $db->getLastItemId();

      if($newImageExt == "")
      {
        array_push($messages, "表紙画像のアップロードに失敗しました。ファイルタイプが判別できません");
      }
      else if($newItemId === FALSE || $newItemId <= 0)
      {
        // 追加したはずのItemIDが見つからない。バグ？
        array_push($message, "表紙画像のアップロードに失敗しました");
      }
      else
      {
        $newImageFileName = BOOK_IMAGE_DIR . "/user_" . $newItemId . "." . $newImageExt;
        if(move_uploaded_file($_FILES['newBookImage']['tmp_name'], $newImageFileName))
        {
          //すでに「項目を追加しました」メッセージが出ているため、ここでは何も出力しない
          //array_push($messages, "表紙画像をアップロードしました");
        }
        else
        {
          array_push($messages, "表紙画像のアップロードに失敗しました");
        }

      }
    }
  } // if($arg["action"] == "addItem" && isset($arg["newPlace"]) && isset($arg["newState"]))


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

      // アップロード画像がある場合は削除
      if(file_exists(BOOK_IMAGE_DIR . "/user_" . $arg["targetItem"] . ".jpg"))
      {
        unlink(BOOK_IMAGE_DIR . "/user_" . $arg["targetItem"] . ".jpg");
      }
      else if(file_exists(BOOK_IMAGE_DIR . "/user_" . $arg["targetItem"] . ".png"))
      {
        unlink(BOOK_IMAGE_DIR . "/user_" . $arg["targetItem"] . ".png");
     }
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

if(isset($arg["view"]) && $arg["view"] == "image")
{
  $imageOnlyView = TRUE;
  $pageCount = 1;
  $currentPage = 1;  
  $startNum = 1;
  $numOfItems = $itemCount;
}
else
{
  $imageOnlyView = FALSE;

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

  $startNum = ($currentPage - 1) * ITEMS_PER_PAGE + 1;
  $numOfItems = ITEMS_PER_PAGE;
}


// ---------- メッセージがある場合のみメッセージ表示 ---------- 
printMessages($messages);


// ---------- 新規登録 ----------
?>

<div id="newItemDiv" style="display: none">

<form method="POST" action="index.php" enctype="multipart/form-data">
<input type="hidden" name="MAX_FILE_SIZE" value="1048576">

<input type="hidden" name="action" value="addItem">

<table border=0>

<tr><td>商品コード
    <td><input type="text" id="newItemCode" name="newItemCode" size="40" value="<?= htmlspecialchars($addItemDefaultItemCode); ?>" placeholder="ISBN、ASIN、AmazonのURL(短縮してないもの)">
</tr>

<tr><td colspan=2>&nbsp;

<tr><td>保管場所
    <td><select name="newPlace">
      <?php foreach ($placeList as $place) { ?>
        <option value="<?= htmlspecialchars($place['id']); ?>"><?= htmlspecialchars($place["place"]); ?></option>
      <?php } ?>
      </select>

<tr><td>未読既読状態
    <td><select name="newState">
    <?php foreach ($stateList as $state) { ?>
      <option value="<?= htmlspecialchars($state['id']); ?>"><?= htmlspecialchars($state["state"]); ?></option>
    <?php } ?>
    </select>

<tr><td colspan=2>&nbsp;
<tr><td colspan=2>Amazonにないアイテムの場合<br>

<tr><td>表紙画像   <td><input type="file" name="newBookImage" size="40">
<tr><td>タイトル   <td><input type="text" name="newTitle"     size="40" value="<?= htmlspecialchars($addItemDefaultTitle); ?>">
<tr><td>著者       <td><input type="text" name="newAuthor"    size="40" value="<?= htmlspecialchars($addItemDefaultAuthor); ?>">
<tr><td>出版社     <td><input type="text" name="newPublisher" size="40" value="<?= htmlspecialchars($addItemDefaultPublisher); ?>">

<tr><td colspan=2>&nbsp;

<tr><td>&nbsp;<td><input type="submit" value="アイテムを登録する">

</table>
</form>

<br>

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

<tr><td colspan=2>&nbsp;

<tr><td>表示形式
<td><select name="view">
  <option value="detail" selected>詳細表示</option>
  <option value="image">表紙のみを一覧表示</option>
</select>

<tr><td>&nbsp;     <td><input type="submit" value="この条件で検索する">

</table>
</form>

<br>

</div>

<?php
// ---------- ページナビゲーション ---------- 
printItemPageLink("index.php", $currentPage, $pageCount, $itemCount, $selectedPlace, $selectedState, $selectedTag, $selectedId, $selectedItemCode, $selectedTitle, $selectedAuthor, $selectedPublisher, $selectedMemo);
?>
<hr>

<?php
// ---------- アイテム一覧 ---------- 
printItemList($ama, $db, $itemList, $startNum, $numOfItems, $imageOnlyView);
?>

<?php
// ---------- ページナビゲーション ---------- 
printItemPageLink("index.php", $currentPage, $pageCount, $itemCount, $selectedPlace, $selectedState, $selectedTag, $selectedId, $selectedItemCode, $selectedTitle, $selectedAuthor, $selectedPublisher, $selectedMemo);
?>
<br>

<?php
// ---------- フッタ ---------- 
require_once(dirname(__FILE__) . '/lib/footer.php');
?>
