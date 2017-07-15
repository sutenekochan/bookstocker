<?php

// ========== 共通で使われる関数の定義 ==========
//
// containsHtmlSqlSpecialCharactors($str);
//   $strにHTMLやSQL的にまずい文字が含まれている場合TRUE、それ以外だとFALSEが帰る



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




?>