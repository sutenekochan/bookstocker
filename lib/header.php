<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>BookStock - 所蔵本の保管場所管理</title>
  <link rel="stylesheet" type="text/css" href="style/bookstocker.css">
</head>

<body>

<script><!--
  var itemDisplayState = "none";  // none,newItem,filter,search の何れか

  function toggleItemDisplay(areaName)
  {
    var areaDivName = areaName + "Div";
    if(itemDisplayState == areaName)
    {
      var targetDiv;
      targetDiv = document.getElementById("newItemDiv");  if(null !== targetDiv) { targetDiv.style.display = "none"; }
      targetDiv = document.getElementById("filterDiv");   if(null !== targetDiv) { targetDiv.style.display = "none"; }
      targetDiv = document.getElementById("searchDiv");   if(null !== targetDiv) { targetDiv.style.display = "none"; }
      itemDisplayState = "none";
    }
    else
    {
      var targetDiv;
      targetDiv = document.getElementById("newItemDiv");  if(null !== targetDiv) { targetDiv.style.display = "none"; }
      targetDiv = document.getElementById("filterDiv");   if(null !== targetDiv) { targetDiv.style.display = "none"; }
      targetDiv = document.getElementById("searchDiv");   if(null !== targetDiv) { targetDiv.style.display = "none"; }
      targetDiv = document.getElementById(areaDivName);   if(null !== targetDiv) { targetDiv.style.display = "block"; }
      itemDisplayState = areaName;
    }
  }
-->
</script>

<div class="menu">
 <div class="menuicon"><a class="menulink" href="index.php"><img src="img/bookstocker_cat.png"><br>蔵書一覧</a></div>
 <div class="menuicon"><a class="menulink" href="#" onclick="toggleItemDisplay('newItem');"><img src="img/readbook_cat.png"><br>新規登録<a></div>
 <div class="menuicon"><a class="menulink" href="#" onclick="toggleItemDisplay('search');"><img src="img/search_cat.png"><br>検索</a></div>
 <div class="menuicon"><a class="menulink" href="setting.php"><img src="img/box_cat.png"><br>設定</a></div>
</div>
<br clear="all">
<br>
