<?php

// ========== 引数の整理とサニタイズ ==========
//
// リクエストに含まれている QUERY_STRING や POST_MESSAGE を整理する
// 指定できる QUERY_STRING 等は query_string.md を参照のこと
//
// このclassで行うのは、型チェックのみであって、意味的なチェックはしていない。
// itemCode 等、文字列型であることのチェックのみで、それがISBNとして意味がある文字列かはチェックしていない。
//
// 型にあわない引数が渡されたときなどは、エラー情報を getLastError で得られる。配列型で、エラーが無いときは空の配列になる。
//
//
// 使用法
//
// $rp = requestParser::createArgArrayFromArgv($argv);
//   コマンドラインからの起動の場合に使う。$argvを$_GET等と同一形式に変換するサポート関数
//   foo=bar形式になっていないものは黙って破棄する
//
// $rp = new requestParser($_GET, $_POST);
//   引数をparse、型チェックする
//   引数は $_GET と $_POST を推奨、$_REQUEST は Cookie を含むため非推奨
//
//   具体的には以下のようにnewすることになる
//     if(PHP_SAPI == 'cli') {
//       $parsedArgv = requestParser::createArgArrayFromArgv($argv);
//       $rp = new requestParser([], $parsedArgv);
//     } else {
//       $rp = new requestParser($_GET, $_POST);
//     }
//
//
// $arg = $rp->getAllArg();
//   全てのparse済引数を連想配列で返す
//
// $errString = $rp->getErrorMessagesAndClear();
//   エラー文字列を配列で返す。エラーがないならNULLになる
//
//
// 内部関数
//
// $var = parseIntSingle1($arr1, $paramName);            // int型をparseして返す。errorMessage等の処理も行う。エラー時はNULLが帰る
// $var = parseIntSingle2($arr1, $arr2, $paramName);     // 関数 ～1 は引数が1個の場合、～2 は2個の場合
// $var = parseStringSingle1($arr1, $paramName, $spaceIsDelimiter = TRUE);         // String型の場合。半角スペースをdelimiterと扱うかどうかのフラグを指定する
// $var = parseStringSingle2($arr1, $arr2, $paramName, $spaceIsDelimiter = TRUE);
// $var = parseRawSingle1($arr1, $paramName, $spaceIsDelimiter = TRUE);            // 何のチェックも行われない場合。出力には HTMLSpecialChars を含む場合がある。
// $var = parseRawSingle2($arr1, $arr2, $paramName, $spaceIsDelimiter = TRUE);
//
// $arr = parseIntArray1($arr1, $paramName);             // int型が","で区切られた文字列をparseして配列にする。errorMessage等の処理も行う。エラー時はNULLが帰る
//                                                       // 空白文字列のみ指定の場合、パラメタが無いのと同等とされる
// $arr = parseIntArray2($arr1, $arr2, $paramName);
// $arr = parseStringArray1($arr1, $paramName, $spaceIsDelimiter = TRUE);          // String型
// $arr = parseStringArray2($arr1, $arr2, $paramName, $spaceIsDelimiter = TRUE);
// $arr = parseRawArray1($arr1, $paramName, $spaceIsDelimiter = TRUE);             // Raw型
// $arr = parseRawArray2($arr1, $arr2, $paramName, $spaceIsDelimiter = TRUE);
//
//   引数  ：$str1      : parse対象の文字列(引数がNULLの場合もありうる)
//           $str2      :
//           $paramName : errorMessages に入れるパラメータの名前
//
//
// $arr = function parseIntArraySub($in);                // int型が","で区切られた文字列をparseして配列にする。戻り値[]は値がなかったことを、NULLはエラーがあったことを示す
// $arr = function parseStringArraySub($in);
//

require_once(__DIR__ . '/function.php');

class requestParser
{
  private $arg;
  private $errorMessages = [];


  public static function createArgArrayFromArgv($argv)
  {
    $argv2 = $argv;
    array_shift($argv2);
    $argv3 = [];
    foreach($argv2 as $i)
    {
      $j= explode("=", $i, 2);
      if(isset($j[1]))
      {
        $argv3[$j[0]] = $j[1];
      }
    }
    return $argv3;
  }

  // ---------- コンストラクタ。引数必須 ----------
  public function __construct(array $queryString, array $postMessage)
  {
    $this->arg = [];

    $v = $this->parseIntSingle2   ($queryString, $postMessage, "p"                    );  if($v !== NULL) {  $this->arg["p"] = $v;               }
    $v = $this->parseStringSingle2($queryString, $postMessage, "view"                 );  if($v !== NULL) {  $this->arg["view"] = $v;            }
    $v = $this->parseIntArray2    ($queryString, $postMessage, "id"                   );  if($v !== NULL) {  $this->arg["id"] = $v;              }
    $v = $this->parseStringArray2 ($queryString, $postMessage, "itemCode"             );  if($v !== NULL) {  $this->arg["itemCode"] = $v;        }
    $v = $this->parseIntArray2    ($queryString, $postMessage, "place"                );  if($v !== NULL) {  $this->arg["place"] = $v;           }
    $v = $this->parseIntArray2    ($queryString, $postMessage, "state"                );  if($v !== NULL) {  $this->arg["state"] = $v;           }
    $v = $this->parseIntArray2    ($queryString, $postMessage, "tag"                  );  if($v !== NULL) {  $this->arg["tag"] = $v;             }
    $v = $this->parseStringArray2 ($queryString, $postMessage, "title"                );  if($v !== NULL) {  $this->arg["title"] = $v;           }
    $v = $this->parseStringArray2 ($queryString, $postMessage, "author"               );  if($v !== NULL) {  $this->arg["author"] = $v;          }
    $v = $this->parseStringArray2 ($queryString, $postMessage, "publisher"            );  if($v !== NULL) {  $this->arg["publisher"] = $v;       }
    $v = $this->parseStringArray2 ($queryString, $postMessage, "memo"                 );  if($v !== NULL) {  $this->arg["memo"] = $v;            }
    $v = $this->parseStringSingle1(              $postMessage, "action"               );  if($v !== NULL) {  $this->arg["action"] = $v;          }
    $v = $this->parseStringSingle1(              $postMessage, "targetItem"           );  if($v !== NULL) {  $this->arg["targetItem"] = $v;      }
    $v = $this->parseIntSingle1   (              $postMessage, "targetPlace"          );  if($v !== NULL) {  $this->arg["targetPlace"] = $v;     }
    $v = $this->parseIntSingle1   (              $postMessage, "targetState"          );  if($v !== NULL) {  $this->arg["targetState"] = $v;     }
    $v = $this->parseIntSingle1   (              $postMessage, "targetTag"            );  if($v !== NULL) {  $this->arg["targetTag"] = $v;       }
    $v = $this->parseRawSingle1   (              $postMessage, "newItemCode"          );  if($v !== NULL) {  $this->arg["newItemCode"] = $v;     }
    $v = $this->parseIntSingle1   (              $postMessage, "newItemJan"           );  if($v !== NULL) {  $this->arg["newItemJan"] = $v;      }
    $v = $this->parseStringSingle1(              $postMessage, "newPlace"             );  if($v !== NULL) {  $this->arg["newPlace"] = $v;        }
    $v = $this->parseStringSingle1(              $postMessage, "newState"             );  if($v !== NULL) {  $this->arg["newState"] = $v;        }
    $v = $this->parseStringSingle1(              $postMessage, "newTag"        , FALSE);  if($v !== NULL) {  $this->arg["newTag"] = $v;          }
    $v = $this->parseStringSingle1(              $postMessage, "newTitle"      , FALSE);  if($v !== NULL) {  $this->arg["newTitle"] = $v;        }
    $v = $this->parseStringSingle1(              $postMessage, "newAuthor"     , FALSE);  if($v !== NULL) {  $this->arg["newAuthor"] = $v;       }
    $v = $this->parseStringSingle1(              $postMessage, "newPublisher"  , FALSE);  if($v !== NULL) {  $this->arg["newPublisher"] = $v;    }
    $v = $this->parseStringSingle1(              $postMessage, "newMemo"       , FALSE);  if($v !== NULL) {  $this->arg["newMemo"] = $v;         }
    $v = $this->parseStringSingle1(              $postMessage, "deleteMemoFlag"       );  if($v !== NULL) {  $this->arg["deleteMemoFlag"] = $v;  }
  }


  // ---------- 結果を得る ----------
  public function getAllArg()
  {
    return $this->arg;
  }


  // ---------- エラーを得る ----------
  public function getErrorMessagesAndClear()
  {
    $msgs = $this->errorMessages;
    $this->errorMessages = [];
    return $msgs;
  }


  // ---------- 内部関数：引数のcheck(int) ----------
  private function parseIntSingle2($arr1, $arr2, $paramName)
  {
    $out = NULL;

    $tmpArr = $this->parseIntArray2($arr1, $arr2, $paramName);
    if($tmpArr !== NULL && $tmpArr !== [])
    {
      if(count($tmpArr) == 1) { $out = array_shift($tmpArr);  }
      else                    {  array_push($this->errorMessages, "パラメータ[" . $paramName . "]には1つの値のみ設定できます");  }
    }

    return $out;
  }


  private function parseIntSingle1($arr1, $paramName)
  {
    $out = NULL;
  
    $tmpArr = $this->parseIntArray1($arr1, $paramName);
    if($tmpArr !== NULL && $tmpArr !== [])
    {
      if(count($tmpArr) == 1) { $out = array_shift($tmpArr);  }
      else                    {  array_push($this->errorMessages, "パラメータ[" . $paramName . "]には1つの値のみ設定できます");  }
    }

    return $out;
  }



  // ---------- 内部関数：引数のcheck(String) ----------
  private function parseStringSingle2($arr1, $arr2, $paramName, $spaceIsDelimiter = TRUE)
  {
    $out = NULL;

    $tmpArr = $this->parseStringArray2($arr1, $arr2, $paramName, $spaceIsDelimiter);
    if($tmpArr !== NULL && $tmpArr !== [])
    {
      if(count($tmpArr) == 1) { $out = array_shift($tmpArr);  }
      else                    {  array_push($this->errorMessages, "パラメータ[" . $paramName . "]には1つの値のみ設定できます");  }
    }
    return $out;
  }


  private function parseStringSingle1($arr1, $paramName, $spaceIsDelimiter = TRUE)
  {
    $out = NULL;
  
    $tmpArr = $this->parseStringArray1($arr1, $paramName, $spaceIsDelimiter);
    if($tmpArr !== NULL && $tmpArr !== [])
    {
      if(count($tmpArr) == 1) { $out = array_shift($tmpArr);  }
      else                    {  array_push($this->errorMessages, "パラメータ[" . $paramName . "]には1つの値のみ設定できます");  }
    }

    return $out;
  }


  // ---------- 内部関数：引数のcheck(Raw) ----------
  private function parseRawSingle2($arr1, $arr2, $paramName, $spaceIsDelimiter = TRUE)
  {
    $out = NULL;

    $tmpArr = $this->parseRawArray2($arr1, $arr2, $paramName, $spaceIsDelimiter);
    if($tmpArr !== NULL && $tmpArr !== [])
    {
      if(count($tmpArr) == 1) { $out = array_shift($tmpArr);  }
      else                    {  array_push($this->errorMessages, "パラメータ[" . $paramName . "]には1つの値のみ設定できます");  }
    }
    return $out;
  }

  private function parseRawSingle1($arr1, $paramName, $spaceIsDelimiter = TRUE)
  {
    $out = NULL;
  
    $tmpArr = $this->parseRawArray1($arr1, $paramName, $spaceIsDelimiter);
    if($tmpArr !== NULL && $tmpArr !== [])
    {
      if(count($tmpArr) == 1) { $out = array_shift($tmpArr);  }
      else                    {  array_push($this->errorMessages, "パラメータ[" . $paramName . "]には1つの値のみ設定できます");  }
    }

    return $out;
  }


  // ---------- 内部関数：引数のcheck(intの配列) ----------
  private function parseIntArray2($arr1, $arr2, $paramName)
  {
    $out = NULL;

    if(isset($arr1[$paramName]) && isset($arr2[$paramName])) {  array_push($this->errorMessages, "GETとPOSTの両方でパラメータ[" . $paramName . "]が指定されました");  }
    else if(isset($arr1[$paramName]))                        {  $out = $this->parseIntArray1($arr1, $paramName);  }
    else if(isset($arr2[$paramName]))                        {  $out = $this->parseIntArray1($arr2, $paramName);  }

    return $out;
  }


  private function parseIntArray1($arr1, $paramName)
  {
    $out = NULL;
  
    if(isset($arr1[$paramName]))
    {
      $out = $this->parseIntArraySub($arr1[$paramName]);
      if($out === NULL) {  array_push($this->errorMessages, "パラメータ[" . $paramName . "]の値が正しくありません");  }
    }

    return $out;
  }


  private function parseIntArraySub($in)
  {
    $out = array();
    $flag = TRUE;

    foreach(preg_split("/[\s,]+/", $in) as $r)
    {
      if(is_numeric($r)) {  array_push($out, (int)($r));  }
      else if($r != "")  {  $flag = FALSE;  }
    }

    if($flag == FALSE)
    {
      return NULL;
    }
    else
    {
      return $out;
    }
  }


  // ---------- 内部関数：引数のcheck(Stringの配列) ----------
  private function parseStringArray2($arr1, $arr2, $paramName, $spaceIsDelimiter = TRUE)
  {
    $out = NULL;

    if(isset($arr1[$paramName]) && isset($arr2[$paramName])) {  array_push($this->errorMessages, "GETとPOSTの両方でパラメータ[" . $paramName . "]が指定されました");  }
    else if(isset($arr1[$paramName]))                        {  $out = $this->parseStringArray1($arr1, $paramName, $spaceIsDelimiter);  }
    else if(isset($arr2[$paramName]))                        {  $out = $this->parseStringArray1($arr2, $paramName, $spaceIsDelimiter);  }

    return $out;
  }


  private function parseStringArray1($arr1, $paramName, $spaceIsDelimiter = TRUE)
  {
    $out = NULL;
  
    if(isset($arr1[$paramName]))
    {
      $out = $this->parseStringArraySub($arr1[$paramName], $spaceIsDelimiter);
      if($out === NULL) {  array_push($this->errorMessages, "パラメータ[" . $paramName . "]の値が正しくありません");  }
    }

    return $out;
  }


  private function parseStringArraySub($in, $spaceIsDelimiter = TRUE)
  {
    $out = array();
    $flag = TRUE;

    if($spaceIsDelimiter) { $delimiter = "/[\s,]+/"; }
    else                  { $delimiter = "/[\f\n\r\t\v,]+/"; }

    foreach(preg_split($delimiter, $in) as $r)
    {
      $r = trim($r);
      if(containsHtmlSqlSpecialCharactors($r)) { $flag = FALSE;  }
      else if($r != "")                        { array_push($out, (String)($r));  }
    }

    if($flag == FALSE)
    {
      return NULL;
    }
    else
    {
      return $out;
    }
  }


  // ---------- 内部関数：引数のcheck(Rawの配列) ----------
   private function parseRawArray2($arr1, $arr2, $paramName, $spaceIsDelimiter = TRUE)
  {
    $out = NULL;

    if(isset($arr1[$paramName]) && isset($arr2[$paramName])) {  array_push($this->errorMessages, "GETとPOSTの両方でパラメータ[" . $paramName . "]が指定されました");  }
    else if(isset($arr1[$paramName]))                        {  $out = $this->parseRawArray1($arr1, $paramName, $spaceIsDelimite);  }
    else if(isset($arr2[$paramName]))                        {  $out = $this->parseRawArray1($arr2, $paramName, $spaceIsDelimite);  }

    return $out;
  }

  private function parseRawArray1($arr1, $paramName, $spaceIsDelimiter = TRUE)
  {
    $out = NULL;
  
    if(isset($arr1[$paramName]))
    {
      $out = $this->parseRawArraySub($arr1[$paramName], $spaceIsDelimiter);
      if($out === NULL) {  array_push($this->errorMessages, "パラメータ[" . $paramName . "]の値が正しくありません");  }
    }

    return $out;
  }


  private function parseRawArraySub($in, $spaceIsDelimiter = TRUE)
  {
    $out = array();

    if($spaceIsDelimiter) { $delimiter = "/[\s,]+/"; }
    else                  { $delimiter = "/[\f\n\r\t\v,]+/"; }

    foreach(preg_split($delimiter, $in) as $r)
    {
      $r = trim($r);
      if($r != "") { array_push($out, (String)($r));  }
    }

    return $out;
  }


}


?>
