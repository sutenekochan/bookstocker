<?php

// ========== アイテム一覧を表示する ==========
//
// printItemList($ama, $db, $itemList, $startNum, $numOfItems, $placeList = NULL, $stateList = NULL);
//   $ama                               // amazonApiクラス
//   $db                                // BookStockDBクラス
//   $itemList = $db->getItemList();    // アイテムリスト、アイテムの二重連想配列が入る
//   $startNum = 1;                     // $itemList の何番目の項目から開始し、いくつ表示するか (最初の項目は1と数える)
//   $numOfItems = 10;
//
// printItemPageLink($url, $currentPage, $maxPage, $allItemCount = NULL, 
//     $searchPlace = [], $searchState = [], $searchTag = [], $searchId = [], $searchItemCode = [], $searchTitle = [], $searchAuthor = [], $searchPublisher = [], $searchMemo = []);
//   $url                  // index.php 等を指定
//   $currentPage          // 現在のページ
//   $maxPage              // 最終ページ
//   $allItemCount         // 全〇件の数値
//   $search....           // 検索条件


require_once(dirname(__FILE__) . '/bookstockerdb.php');
require_once(dirname(__FILE__) . '/amazonapi.php');



function printItemList($ama, $db, $itemList, $startNum, $numOfItems)
{
  $itemCount = count($itemList);
  $startCount = $startNum - 1;
  if($startCount < 0) { $startCount = 0; }
  $stopCount  = $startNum + $numOfItems - 1;
  if($stopCount > $itemCount) { $stopCount = $itemCount; }

  for ($count = $startCount; $count < $stopCount; $count++)
  {
    $item = $itemList[$count];

    $itemInfo = $ama->searchCacheByAsin($item["itemid"]);

    if ($item["datasource"] == BookStockerDB::DataSource_UserDefined)
    {
      // ユーザ定義のデータがある場合
      $imageUrl        = "img/NoImage.png";
      $imageWidth      = 115;
      $imageHeight     = 160;
      $itemid          = "";  if(isset($item["itemid"]))    { $itemid = $item["itemid"]; }
      $detailPageUrl   = NULL;
      $title           = $item["title"];
      $author          = "";  if(isset($item["author"]))    { $author = $item["author"]; }
      $publisher       = "";  if(isset($item["publisher"])) { $publisher = $item["publisher"]; }
      $publicationDate = "";
      $binding         = "";
      $numberOfPages   = "";
      $lowestPrice     = "";
      $memo            = "";  if(isset($item["memo"]))      { $memo = $item["memo"]; }
    }
    else if($itemInfo !== NULL)
    {
      // Amazonのデータがある場合
      $imageUrl        = $itemInfo->getMediumImageUrl();
      $imageWidth      = $itemInfo->getMediumImageWidth();
      $imageHeight     = $itemInfo->getMediumImageHeight();
      $itemid          = $itemInfo->getAsin();
      $detailPageUrl   = $itemInfo->getDetailPageUrl();
      $title           = $itemInfo->getTitle();
      $author          = $itemInfo->getAuthor();
      $publisher       = $itemInfo->getPublisher();
      $publicationDate = $itemInfo->getPublicationDate();
      $binding         = $itemInfo->getBinding();
      $numberOfPages   = $itemInfo->getNumberOfPages();
      $lowestPrice     = $itemInfo->getLowestNewPrice();
      $memo            = "";  if(isset($item["memo"]))      { $memo = $item["memo"]; }
    }
    else
    {
      // アイテムが見つからない場合
      $imageUrl = NULL;
      $message = '';
      if (isset($item['itemid'])) { $message .= "ID="    . $item['itemid'] . " "; }
      if (isset($item['title']))  { $message .= "Title=" . $item['title']; }
    }

  $placeList = $db->getPlaceList();
  $stateList = $db->getStateList();
  $tagList   = $db->getTagList();


?>

<?php
    if ($imageUrl === NULL) {
?>
  <!-- ---------- Item ID: <?= htmlspecialchars($item["id"]); ?> ---------- -->
  アイテムが見つかりません：<?= htmlspecialchars($message); ?>">
<?php
    }
    else  // if ($imageUrl === NULL)
    {
?>
  <!-- ---------- Item ID: <?= htmlspecialchars($item["id"]); ?> ---------- -->
  <div class="itemImageArea">
  <img class="detailimage" src="<?= $imageUrl ?>" width="<?= $imageWidth ?>" height="<?= $imageHeight ?>">
  </div>

  <div class="itemDetailArea">
   <span class="detailText">商品コード: <?= $itemid ?></span>

   <span class="deleteIconArea">
    <form method="POST" action="index.php">
     <input type="hidden" name="action" value="delItem">
     <input type="hidden" name="targetItem" value="<?= htmlspecialchars($item["id"]); ?>">
     <input type="image" src="img/delete_red.png" alt="削除" class="deleteIconImage" onclick="return confirm('本当に削除する？')">
     <!-- input type="submit" value="削除" -->
    </form>
   </span>
   <br>

  <?php if ($detailPageUrl !== NULL) { ?>
   <span class="amazonLinkArea">
    <a target="_blank" href="<?= $detailPageUrl ?>"><img src="img/assocbutt_or_amz._V371070157_.png"></a>
   </span>
  <?php } ?>

   <a href="index.php?id=<?= htmlspecialchars($item["id"]); ?>"><?= $title ?></a><br>

   <?= $author ?><br>
   <span class="detailText"><?= $publisher ?>
   <?php if ($publicationDate != "") { ?> / <?= $publicationDate ?><?php } ?>
   <?php if ($binding != "")         { ?> / <?= $binding ?><?php } ?>
   <?php if ($numberOfPages != "")   { ?> / <?= $numberOfPages ?>p<?php } ?>
   <?php if ($lowestPrice != "")     { ?> / <?= $lowestPrice ?><?php } ?>
   </span><br>

   <form class="placeArea" method="POST" action="index.php">
    <input type="hidden" name="action" value="modifyItem">
    <input type="hidden" name="targetItem" value="<?= htmlspecialchars($item["id"]); ?>">
    <select name="newPlace" onchange="this.form.submit()">
     <?php foreach ($placeList as $place) { ?>
     <option value="<?= htmlspecialchars($place['id']); ?>" <?php if($place['id'] == $item["pid"]){ ?>selected <?php } ?> ><?= htmlspecialchars($place["place"]); ?></option>
     <?php } ?>
    </select>
    <!-- input type="submit" value="保管場所の変更" -->
   </form>

  <form class="stateArea" method="POST" action="index.php">
   <input type="hidden" name="action" value="modifyItem">
   <input type="hidden" name="targetItem" value="<?= htmlspecialchars($item["id"]); ?>">
   <select name="newState" onchange="this.form.submit()">
    <?php foreach ($stateList as $state) { ?>
    <option value="<?= htmlspecialchars($state['id']); ?>" <?php if($state['id'] == $item["sid"]){ ?>selected <?php } ?> ><?= htmlspecialchars($state["state"]); ?></option>
    <?php } ?>
   </select>
   <!-- input type="submit" value="ステータスの変更" -->
   </form>
   <br>

   <form method="POST" action="index.php" class="memoArea">
   <input type="hidden" name="action" value="modifyItem">
   <input type="hidden" name="targetItem" value="<?= htmlspecialchars($item["id"]); ?>">
   <input type="text" name="newMemo" size=30 value="<?= htmlspecialchars($memo); ?>" placeholder="一言メモ">
   <input type="submit" value="メモ更新">
   </form>
   <form method="POST" action="index.php" class="memoArea">
   <input type="hidden" name="action" value="modifyItem">
   <input type="hidden" name="targetItem" value="<?= htmlspecialchars($item["id"]); ?>">
   <input type="hidden" name="deleteMemoFlag" value=1>
   <input type="submit" value="メモ削除">
   </form>
   <br>

  <?php $tagRefList = $db->getTagRefList((int)($item["id"])); foreach($tagRefList as $tagRef) { ?>
  <span class="tagTextArea">
    <a class="tagTextLink" href="index.php?tag=<?= htmlspecialchars($tagRef['tid']); ?>"><?= htmlspecialchars($tagRef['tag']); ?></a>
    <form method="POST" action="index.php" class="tagDeleteArea">
     <input type="hidden" name="action" value="delTagRef">
     <input type="hidden" name="targetItem" value="<?= htmlspecialchars($item["id"]); ?>">
     <input type="hidden" name="targetTag" value="<?= htmlspecialchars($tagRef['tid']); ?>">
     <input type="image" src="img/delete_red.png" alt="削除" class="tagDeleteImage">
     <!-- input type="submit" value="削除" -->
    </form>
  </span>&nbsp;
  <?php } ?>

  <form method="POST" action="index.php" class="tagAddForm">
    <input type="hidden" name="action" value="addTagRef">
    <input type="hidden" name="targetItem" value="<?= htmlspecialchars($item["id"]); ?>">
    <select name="targetTag" onchange="this.form.submit()" class="tagAddFormSelect">
      <option value="0" selected="">タグ追加</option>
      <?php foreach ($tagList as $tag) {  ?>
      <option value="<?= htmlspecialchars($tag['id']); ?>"><?= htmlspecialchars($tag["tag"]); ?></option>
      <?php } ?>
    </select>
    <!-- input type="submit" value="タグの追加" -->
  </form>


   </div>
   <br clear="all">
   <hr>

  <?php
    }  // if ($imageUrl === NULL)
  }  // for ($count = $startCount; $count < $stopCount; $count++)
}  // function printItemList



// ---------- 各ページへのリンクを表示 ----------

function printItemPageLink($url, $currentPage, $maxPage, $allItemCount = NULL, 
  $searchPlace = [], $searchState = [], $searchTag = [], $searchId = [], $searchItemCode = [], $searchTitle = [], $searchAuthor = [], $searchPublisher = [], $searchMemo = [])
{
  $searchText = "";
  if($searchPlace     !== []) {  $searchText .= "&place="     . implode(",", $searchPlace);      }
  if($searchState     !== []) {  $searchText .= "&state="     . implode(",", $searchState);      }
  if($searchTag       !== []) {  $searchText .= "&tag="       . implode(",", $searchTag);        }
  if($searchId        !== []) {  $searchText .= "&id="        . implode(",", $searchId);         }
  if($searchItemCode  !== []) {  $searchText .= "&itemCode="  . implode(",", $searchItemCode);   }
  if($searchTitle     !== []) {  $searchText .= "&title="     . implode(",", $searchTitle);      }
  if($searchAuthor    !== []) {  $searchText .= "&author="    . implode(",", $searchAuthor);     }
  if($searchPublisher !== []) {  $searchText .= "&publisher=" . implode(",", $searchPublisher);  }
  if($searchMemo      !== []) {  $searchText .= "&memo="      . implode(",", $searchMemo);       }
  if($searchText != "") { $searchText = substr($searchText, 1); }

  ?>
  <a href="<?= $url ?><?php if($searchText != ""){ ?>?<?= $searchText ?><?php } ?>"><span class="pageLink">&lt;&lt;最初</span></a>&nbsp;
  <?php

  if($currentPage >= 2)
  {
    $previousPage = $currentPage - 1;
    if($previousPage != 1)
    {
      $previousPageText = "?p=" . $previousPage;
      if($searchText != "") { $previousPageText .= "&" . $searchText; }
    }
    else
    {
      $previousPageText = "";
      if($searchText != "") { $previousPageText .= "?" . $searchText; }
    }
  ?>
  <a href="<?= $url . $previousPageText ?>"><span class="pageLink">&lt;前</span></a>&nbsp;
  <?php
  }

  ?>
  <span class="activePageLink">[<?= $currentPage ?>]</span>&nbsp;
  <?php

  $nextPage = $currentPage + 1;
  if($nextPage <= $maxPage)
  {
    $nextPageText = "?p=" . $nextPage;
    ?>
  <a href="<?= $url . $nextPageText ?><?php if($searchText != ""){ ?>&<?= $searchText ?><?php } ?>"><span class="pageLink">次&gt;</span></a>&nbsp;
    <?php
  }

  if($maxPage != 1)
  {
    $maxPageText = "?p=" . $maxPage;
    if($searchText != "") { $maxPageText .= "&" . $searchText; }
  }
  else
  {
    $maxPageText = "";
    if($searchText != "") { $maxPageText .= "?" . $searchText; }
  }
  ?>
  <a href="<?= $url . $maxPageText?>"><span class="pageLink">最後&gt;&gt;</span></a>&nbsp;
  / 全<?= $maxPage ?>ページ
  <?php if($allItemCount !== NULL) { ?>(<?= htmlspecialchars($allItemCount) ?> 件)<?php } ?>
  <?php
}


?>

