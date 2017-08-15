<?php

// ========== 共通で使われる関数の定義 ==========
//
// containsHtmlSqlSpecialCharactors($str);
//   $strにHTMLやSQL的にまずい文字が含まれている場合TRUE、それ以外だとFALSEが帰る
//
// $asin = normalizeAsin($itemid);
//   引数で与えられた商品コード(ISBN13/ISBN10/ASIN)をASINの形式に変換する
//   注：変換すべき形式でない場合、そのままスルーされ、入力値のまま出力される


// ---------- HTMLやSQL的にまずい文字が含まれているかどうか判別 ----------
function containsHtmlSqlSpecialCharactors($s)
{
  if(strpos($s, "<")  === FALSE &&
     strpos($s, ">")  === FALSE &&
     strpos($s, "&")  === FALSE &&
     strpos($s, '"')  === FALSE &&
     strpos($s, "'")  === FALSE &&
     strpos($s, "%")  === FALSE &&
     strpos($s, "_")  === FALSE &&
     strpos($s, "\\")  === FALSE &&
     strpos($s, "--") === FALSE)
  {
    return FALSE;
  }

  return TRUE;
}

// ---------- ISBN13/ISBN10/ASINをASINに変換 ----------
function normalizeAsin($itemid)
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

  return $itemid;
}



?>