<?php
// ========== Amazonから情報を取ってきてキャッシュに保存するコマンドラインプログラム ==========
// debug用にお使いください。setting.iniが正しければこれで情報を得られるはずです。

require_once(dirname(__FILE__) . '/../setting.ini.php');
require_once(dirname(__FILE__) . '/../lib/error.php');
require_once(dirname(__FILE__) . '/../lib/amazonapi.php');


// ---------- 起動環境チェック ----------
if(php_sapi_name() != "cli")
{
  error_exit("This program is command line only");
}


// ---------- 初期化 ----------
$ama = new amazonApi(AWS_ACCESSKEY, AWS_SECRETKEY, AMAZON_ASSOCIATE_ID, CACHE_DIR);


// ---------- 共通関数 ----------
function usageExit($message = "")
{
  if($message != "")
  {
    print "Error: " . $message . "\n\n";
  }
  print "Usage: " . $_SERVER["PHP_SELF"] . "  [option] itemid\n";
  print " -a --asin: search by asin (default)\n";
  print " -i --isbn: search by isbn\n";
  print "\n";
  exit;
}


// ---------- 引数チェック ----------
$mode = "asin";
$itemid = NULL;

if($argc == 2)  // 商品番号のみ指定
{
  $itemid = $argv[1];
}
else if($argc == 3)  // オプションと商品番号を指定
{
  if($argv[1] == "-a" || $argv[1] == "--asin") { $mode="asin";  $itemid = $argv[2];  }
  if($argv[1] == "-i" || $argv[1] == "--isbn") { $mode="isbn";  $itemid = $argv[2];  }
}

if($itemid === NULL)
{
  usageExit("Invalid Arguement");
}



// 商品コード欄に入力されたものが http:// あるいは https:// で始まる場合、Amazon の URL とみなして変換する
if(strstr($itemid, "http://") == $itemid || strstr($itemid, "https://") == $itemid)
{
  $itemid = AmazonApi::getAsinFromUrl($itemid);
}
if($itemid === NULL)
{
  usageExit("Cannot get item id from amazon URL");
}



// ---------- 情報を取ってくる ----------
// $itemid が13桁で(978|979)で始まる数値の場合、13桁ISBNとみなし、10桁に変換
if(is_numeric($itemid) && strlen($itemid) == 13 && (strstr($itemid, "978") == $itemid || strstr($itemid, "979") == $itemid))
{
  print "Notice: Item code was changed from isbn13 to isbn10\n\n";
  $itemid = amazonApi::isbn13toIsbn10($itemid);
}


print "Getting information, Mode=" . $mode . " / item code=" . $itemid . "\n";


if($mode == "asin")
{
  $newItem = $ama->searchByAsin($itemid);
}
else
{
  $newItem = $ama->searchByIsbn($itemid);
}


if($newItem === NULL)
{
  print "Error: Cannot get information from Amazon.\n";
  print "Error message from amazon:" . $ama->getLastError() . "\n";
  exit;
}


print "XML saved to cache directory (" . CACHE_DIR . ")\n";
print "File name is " . $itemid . ".xml\n";

exit;

?>
