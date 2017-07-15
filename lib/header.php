<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>BookStock - 所蔵本の保管場所管理</title>
  <link rel="stylesheet" type="text/css" href="style/bookstocker.css">
</head>

<script><!--
  var itemDisplayState = "none";  // none,newItem,filter,search の何れか

  function toggleItemDisplay(areaName)
  {
    var areaDivName = areaName + "Div";
    if(itemDisplayState == areaName)
    {
      var targetDiv;
      targetDiv = document.getElementById("newItemDiv");  if(null !== targetDiv) { targetDiv.style.display = "none"; }
      targetDiv = document.getElementById("searchDiv");   if(null !== targetDiv) { targetDiv.style.display = "none"; }
      itemDisplayState = "none";
    }
    else
    {
      var targetDiv;
      targetDiv = document.getElementById("newItemDiv");  if(null !== targetDiv) { targetDiv.style.display = "none"; }
      targetDiv = document.getElementById("searchDiv");   if(null !== targetDiv) { targetDiv.style.display = "none"; }
      targetDiv = document.getElementById(areaDivName);   if(null !== targetDiv) { targetDiv.style.display = "block"; }
      itemDisplayState = areaName;
    }
  };

  function initItemDisplay()
  {
    if("#newItem" == location.hash)
    {
      var targetDiv;
      targetDiv = document.getElementById("newItemDiv");  if(null !== targetDiv) { targetDiv.style.display = "block"; }
      itemDisplayState = "newItem";
    }

    if("#search" == location.hash)
    {
      var targetDiv;
      targetDiv = document.getElementById("searchDiv");  if(null !== targetDiv) { targetDiv.style.display = "block"; }
      itemDisplayState = "search";
    }
  };

-->
</script>

<body onload="initItemDisplay();">


<?php
if(isset($_SERVER['SCRIPT_NAME']) && strlen($_SERVER['SCRIPT_NAME']) > 10 && "/index.php" == substr($_SERVER['SCRIPT_NAME'], -10))
{
  $newItemLink = "#new";
  $searchLink  = "#search";
  $newItemOnClick = "onclick=\"toggleItemDisplay('newItem');\"";
  $searchOnClick = "onclick=\"toggleItemDisplay('search');\"";
}
else
{
  $newItemLink = "index.php#newItem";
  $searchLink  = "index.php#search";
  $newItemOnClick = "";
  $searchOnClick = "";
}
?>

<div class="menu">
 <div class="menuicon"><a class="menulink" href="index.php"><img src="img/bookstocker_cat.png"><br>蔵書一覧</a></div>
 <div class="menuicon"><a class="menulink" href="<?= $newItemLink ?>" <?= $newItemOnClick ?>><img src="img/readbook_cat.png"><br>新規登録<a></div>
 <div class="menuicon"><a class="menulink" href="<?= $searchLink ?>" <?= $searchOnClick ?>><img src="img/search_cat.png"><br>検索</a></div>
 <div class="menuicon"><a class="menulink" href="setting.php"><img src="img/box_cat.png"><br>設定</a></div>
</div>
<br clear="all">
<br>
