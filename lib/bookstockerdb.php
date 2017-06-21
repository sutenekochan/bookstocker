<?php

require_once(__DIR__ . '/function.php');


// ========== Bookstocker の DB 関連のクラス ==========
//
// singleton class として実装してある。
// new は行わない、getInstance でインスタンスを得て、それを操作する
//
// どの関数でも、致命的なエラーがあった時には Exception を起こす。
//
// テーブル構造 (詳しくは function initDb あたりを見ること)
//     place: 保存場所        ： id=serial, place=text
//     state: 未読既読等の状態： id=serial, state=text
//     item: 所持品           ： id=serial, itemid=商品コード(ASIN：Amazonの商品コード等)、place=保存場所、state=未読既読等の状態
//                               title=商品名, author=著者, publisher=出版社
//                               運用として、itemid または title のどちらかが必須 (DB的な制約はつけていない)
// いちおう外部制約を付けてはあるが、SQLiteのバージョンによっては外部制約が機能しない。
// indexは作らない (どうせ作るほどデータ入れないだろう)
//
//
//
// 使用法 (引数等の情報は各APIの説明を参照のこと)
//
// ・初期化 (Singleton なので new は行わない)
//   $db = BookStockerDB::getInstance();             // エラー時NULLが帰る
//   $db->init(DB_DSN, DB_USERNAME, DB_PASSWORD);
//
// ・エラー情報を文字列で得る
//   $errString = $db->getLastError();
//
// ・場所(placeテーブル)を操作
//   $placeList = $db->getPlaceList();               // 二重連想配列が帰る
//   $db->addPlace($place);                          // エラー時FALSEが帰る
//   $db->deletePlace($id);                          // エラー時FALSEが帰る
//
// ・未読既読状態(stateテーブル)を操作
//   $stateList = $db->getStateList();               // 二重連想配列が帰る
//   $db->addState($state);                          // エラー時FALSEが帰る
//   $db->deleteState($id);                          // エラー時FALSEが帰る
//
// ・所持品(itemテーブル)を操作
//   $itemList = $db->getItemList($place = 0, $state = 0));
//                                                   // アイテム一覧を得る。二重連想配列が帰る
//   $itemList = $db->searchItem($itemid, $place, $state, $title, $author, $publisher, $memo);
//                                                   // 詳細な検索
//   $db->addItem($datasource, $itemid, $title, $author, $publisher, $place, $state);
//                                                   // アイテム追加。エラー時FALSEが帰る。検索のために Amazon にあるアイテムでも $title 等を指定すること。
//                                                   // $datasource には BookStockerDB::DataSource_Amazon または BookStockerDB::DataSource_UserDefined を指定
//   $db->deleteItem($id);                           // エラー時FALSEが帰る
//   $db->modifyItemPlace($itemId, $placeId);
//   $db->modifyItemState($itemId, $stateId);
//   $db->modifyItemMemo($itemId, $memo);            // メモを消去したい場合は $memo を NULL にする
//
// ・サポート関数
//   BookStockerDB::makeStringParameter($str);               // $strにHTMLやSQL的にまずい文字が含まれている場合NULL、それ以外だと$strが帰る


class BookStockerDB
{
  private static $singletonInstance;

  private $dbh;
  private $lastErrorString;


  // ---------- 定数 ----------
  const DataSource_Amazon = 1;
  const DataSource_UserDefined = 2;


  // ---------- Singleton 操作 ----------
  public static function getInstance()
  {
    if (!self::$singletonInstance)
    {
      self::$singletonInstance = new BookStockerDB;
    }
    return self::$singletonInstance;
  }



  // ---------- コンストラクタ等を private で封印 ----------
  final private function __construct() {}
  final private function __clone() {}



  // ---------- 初期化/終了。引数必須 ----------
  public function init($dbDsn, $dbUsername, $dbPassword)
  {
    $this->dbh = $this->openDb($dbDsn, $dbUsername, $dbPassword);
    if($this->dbh !== NULL)
    {
      $this->initDb($dbDsn);
      $this->initTable();
    }
  }



  public function __destruct()
  {
    $this->dbh = NULL;
    self::$singletonInstance = NULL;
  }



  // ---------- 内部関数：DBに接続 ----------
  private function openDb($dbDsn, $dbUsername, $dbPassword)
  {
    try {
      $dbDriverOptions = [];
      $this->dbh = new PDO($dbDsn, $dbUsername, $dbPassword, $dbDriverOptions);

    } catch(PDOException $e) {
      $this->dbh = NULL;
      $errmsg = $e->getMessage();
    }

    if($this->dbh === NULL)
    {
      throw new Exception("DB Connection Failed" . $errmsg);
    }

    return $this->dbh;
  }



  // ---------- 内部関数：DB設定 ----------
  private function initDb($dbDsn)
  {
    // SQLiteにおいて、外部制約の機能ををonにする
    if($this->dbh !== FALSE)
    {
      if(stristr($dbDsn, "sqlite") >= 0)
      {
        $this->dbh->exec("PRAGMA foreign_keys=ON");
      }
    }

    // 静的プレースフォルダを利用。PHP側ではなくDB側でプレースホルダを処理する
    $this->dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);

    // SQL実行でエラーがあったときに黙ってる (execute時に毎回戻り値を確認)
    $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
  }



  // ---------- 内部関数：DB製作 ----------
  private function initTable()
  {
    if($this->dbh != NULL)
    {
      // 保存場所table
      $this->dbh->exec(
        "CREATE TABLE IF NOT EXISTS place (
          id    integer primary key autoincrement,
          place text not null unique
        )");

      // DBに何も入ってない場合に、最初の1個を入れる
      $preparedSql = $this->dbh->query("SELECT count(id) FROM place");
      $result = $preparedSql->fetch();

      if($result[0] == 0)
      {
        $preparedSql = $this->dbh->prepare("INSERT INTO place (place) values (:place)");
        $preparedSql->bindValue(":place", "未整理", PDO::PARAM_STR);
        $preparedSql->execute();

        $preparedSql = $this->dbh->prepare("INSERT INTO place (place) values (:state)");
        $preparedSql->bindValue(":state", "廃棄", PDO::PARAM_STR);
        $preparedSql->execute();
      }


      // 状態table
      $this->dbh->exec(
        "CREATE TABLE IF NOT EXISTS state (
          id    integer primary key autoincrement,
          state text not null unique
        )");

      // DBに何も入ってない場合に、最初の1個を入れる
      $preparedSql = $this->dbh->query("SELECT count(id) FROM state");
      $result = $preparedSql->fetch();

      if($result[0] == 0)
      {
        $preparedSql = $this->dbh->prepare("INSERT INTO state (state) values (:state)");
        $preparedSql->bindValue(":state", "未読", PDO::PARAM_STR);
        $preparedSql->execute();

        $preparedSql = $this->dbh->prepare("INSERT INTO state (state) values (:state)");
        $preparedSql->bindValue(":state", "既読", PDO::PARAM_STR);
        $preparedSql->execute();
      }


      // 所持品table
      // datasource は、amazonアイテムの場合 DataSource_Amazon、ユーザが品名等を入れた場合 DataSource_UserDefined
      // ASINはアルファベットも含むため itemid は text である
      $this->dbh->exec(
        "CREATE TABLE IF NOT EXISTS item (
          id         integer primary key autoincrement,
          datasource integer not null,
          itemid     text unique,
          place      integer not null,
          state      integer not null,
          title      text unique,
          author     text,
          publisher  text,
          memo       text,
        FOREIGN KEY (place) REFERENCES place(id),
        FOREIGN KEY (state) REFERENCES state(id)
        )");
    }
  }



  // ---------- DBのエラー情報を文字列で得る ----------
  public function getLastError()
  {
    return $this->lastErrorString;
  }





  // ---------- SQLのplaceholderに渡す文字列パラメタを作る ----------
  public static function makeStringParameter($s)
  {
    $ret = NULL;
    if(!containsHtmlSqlSpecialCharactors($s))
    {
      $ret = mb_convert_encoding($s, "UTF-8", "UTF-8");  // マルチバイト文字が中途半端に途切れたものを削除
    }

    return $ret;
  }



  // ---------- placeを得る ----------
  public function getPlaceList()
  {
    if($this->dbh != NULL)
    {
      $preparedSql = $this->dbh->query("SELECT * FROM place ORDER BY id");
      $result = $preparedSql->fetchAll();
      return $result;
    }
    else
    {
      return [];
    }
  }



  // ---------- placeを追加 ----------
  // エラー時は FALSE が帰る。エラー内容は getLastError で得られる
  public function addPlace($place)
  {
    $ret = FALSE;
    $place = BookStockerDB::makeStringParameter($place);

    if($this->dbh != NULL && $place !== NULL)
    {
      $preparedSql = $this->dbh->prepare("INSERT INTO place (place) values (:place)");
      $preparedSql->bindValue(":place", (String)$place, PDO::PARAM_STR);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        $this->lastErrorString = $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2];
      }
    }

    return $ret;
  }



  // ---------- placeを削除 ----------
  // エラー時は FALSE が帰る。エラー内容は getLastError で得られる
  public function deletePlace($id)
  {
    $ret = FALSE;

    if($this->dbh != NULL && is_numeric($id))
    {
      $preparedSql = $this->dbh->prepare("DELETE FROM place WHERE id = :id");
      $preparedSql->bindValue(":id", (int)$id, PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        $this->lastErrorString = $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2];
      }
    }

    return $ret;
  }




  // ---------- stateを得る ----------
  public function getStateList()
  {
    if($this->dbh != NULL)
    {
      $preparedSql = $this->dbh->query("SELECT * from state ORDER BY id");
      $result = $preparedSql->fetchAll();
      return $result;
    }
    else
    {
      return [];
    }
  }



  // ---------- stateを追加 ----------
  // エラー時は FALSE が帰る。エラー内容は getLastError で得られる
  public function addState($state)
  {
    $ret = FALSE;
    $state = BookStockerDB::makeStringParameter($state);

    if($this->dbh != NULL && $state !== NULL)
    {
      $preparedSql = $this->dbh->prepare("INSERT INTO state (state) values (:state)");
      $preparedSql->bindValue(":state", (String)$state, PDO::PARAM_STR);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        $this->lastErrorString = $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2];
      }
    }

    return $ret;
  }



  // ---------- stateを削除 ----------
  // エラー時は FALSE が帰る。エラー内容は getLastError で得られる
  public function deleteState($id)
  {
    $ret = FALSE;

    if($this->dbh != NULL && is_numeric($id))
    {
      $preparedSql = $this->dbh->prepare("DELETE FROM state WHERE id = :id");
      $preparedSql->bindValue(":id", (int)$id, PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        $this->lastErrorString = $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2];
      }
    }

    return $ret;
  }




  // ---------- itemを得る ----------
  public function getItemList($place = 0, $state = 0)
  {
    return $this->searchItem(NULL, $place, $state, NULL, NULL, NULL, NULL);
  }



  // ---------- 詳細検索 ----------
  public function searchItem($itemid, $place, $state, $title, $author, $publisher, $memo)
  {
    if($this->dbh != NULL)
    {

      $searchString = "";
      $placeHolder = [];

      if(isset($itemid) && $itemid != "")
      {
        $arr = preg_split("/\s+/", $itemid, -1, PREG_SPLIT_NO_EMPTY);
        $placeHolder = array_merge($placeHolder, $arr);
	foreach ($arr as $a)
	{
	  $searchString .= " AND itemid like ?";
	}
      }

      if(isset($title) && $title != "")
      {
        $arr = preg_split("/\s+/", $title, -1, PREG_SPLIT_NO_EMPTY);
        $placeHolder = array_merge($placeHolder, $arr);
	foreach ($arr as $a)
	{
	  $searchString .= " AND title like ?";
	}
      }

      if(isset($author) && $author != "")
      {
        $arr = preg_split("/\s+/", $author, -1, PREG_SPLIT_NO_EMPTY);
        $placeHolder = array_merge($placeHolder, $arr);
	foreach ($arr as $a)
	{
	  $searchString .= " AND author like ?";
	}
      }

      if(isset($publisher) && $publisher != "")
      {
        $arr = preg_split("/\s+/", $publisher, -1, PREG_SPLIT_NO_EMPTY);
        $placeHolder = array_merge($placeHolder, $arr);
	foreach ($arr as $a)
	{
	  $searchString .= " AND publisher like ?";
	}
      }

      if(isset($memo) && $memo != "")
      {
        $arr = preg_split("/\s+/", $memo, -1, PREG_SPLIT_NO_EMPTY);
        $placeHolder = array_merge($placeHolder, $arr);
	foreach ($arr as $a)
	{
	  $searchString .= " AND memo like ?";
	}
      }

      if($place != 0)
      {
        $searchString .= " AND item.place = ?";
      }

      if($state != 0)
      {
        $searchString .= " AND item.state = ?";
      }


      if($searchString != "")
      {
        $searchString = "WHERE " . substr($searchString, 5);
      }

      $preparedSql = $this->dbh->prepare("
        SELECT item.id, datasource, itemid, place.id AS pid, place.place, state.id AS sid, state.state, title, author, publisher, memo FROM item
        INNER JOIN place ON item.place = place.id
        INNER JOIN state ON item.state = state.id " .
        $searchString .
        " ORDER BY item.id DESC;");

      for ($p=0; $p<count($placeHolder);  $p++)
      {
        $preparedSql->bindValue(($p+1), (String)("%" . (String)$placeHolder[$p] . "%"), PDO::PARAM_STR);
      }
      if($place != 0) { $preparedSql->bindValue(++$p, (int)$place, PDO::PARAM_INT);  }
      if($state != 0) { $preparedSql->bindValue(++$p, (int)$state, PDO::PARAM_INT);  }

      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        $result = $preparedSql->fetchAll();
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        $this->lastErrorString = $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2];
        $result = [];
      }
      return $result;
    }
    else
    {
      return [];
    }
  }



  // ---------- itemを追加 ----------
  // エラー時は FALSE が帰る。エラー内容は getLastError で得られる
  public function addItem($datasource, $itemid, $title, $author, $publisher, $place, $state)
  {
    $ret = FALSE;
    if(isset($itemid))    {  $itemid    = BookStockerDB::makeStringParameter($itemid);     }
    if(isset($title))     {  $title     = BookStockerDB::makeStringParameter($title);      }
    if(isset($author)   ) {  $author    = BookStockerDB::makeStringParameter($author);     }
    if(isset($publisher)) {  $publisher = BookStockerDB::makeStringParameter($publisher);  }

    if($this->dbh != NULL && is_numeric($datasource) &&  ($itemid !== NULL || $title !== NULL) && is_numeric($place) && is_numeric($state))
    {
      $preparedSql = $this->dbh->prepare("INSERT INTO item (datasource, itemid, title, author, publisher, place, state) " .
                                         "values (:datasource, :itemid, :title, :author, :publisher, :place, :state)");

      $preparedSql->bindValue(":datasource",  (int)$datasource,   PDO::PARAM_INT);

      if($itemid !== NULL)
      {
        $preparedSql->bindValue(":itemid",    (String)$itemid,    PDO::PARAM_STR);
      } else {
        $preparedSql->bindValue(":itemid",    NULL,               PDO::PARAM_NULL);
      }

      if($title !== NULL)
      {
        $preparedSql->bindValue(":title",     (String)$title,     PDO::PARAM_STR);
      } else {
        $preparedSql->bindValue(":title",     NULL,               PDO::PARAM_NULL);
      }

      if($author !== NULL)
      {
        $preparedSql->bindValue(":author",    (String)$author,    PDO::PARAM_STR);
      } else {
        $preparedSql->bindValue(":author",    NULL,               PDO::PARAM_NULL);
      }

      if($publisher !== NULL)
      {
        $preparedSql->bindValue(":publisher", (String)$publisher, PDO::PARAM_STR);
      } else {
        $preparedSql->bindValue(":publisher", NULL,               PDO::PARAM_NULL);
      }

      $preparedSql->bindValue(  ":place",     (int)$place,        PDO::PARAM_INT);
      $preparedSql->bindValue(  ":state",     (int)$state,        PDO::PARAM_INT);


      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        $this->lastErrorString = $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2];
      }
    }

    return $ret;
  }



  // ---------- itemを削除 ----------
  // エラー時は FALSE が帰る。エラー内容は getLastError で得られる
  public function deleteItem($id)
  {
    $ret = FALSE;

    if($this->dbh != NULL && is_numeric($id))
    {
      $preparedSql = $this->dbh->prepare("DELETE FROM item WHERE id = :id");
      $preparedSql->bindValue(":id", (int)$id, PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        $this->lastErrorString = $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2];
      }
    }

    return $ret;
  }



  // ---------- item情報の変更 ----------
  // エラー時は FALSE が帰る。エラー内容は getLastError で得られる
  public function modifyItemPlace($itemId, $placeId)
  {
    $ret = FALSE;

    if($this->dbh != NULL && is_numeric($itemId) && is_numeric($placeId))
    {
      $preparedSql = $this->dbh->prepare("UPDATE item SET place = :place WHERE id = :id");
      $preparedSql->bindValue(":place", (int)$placeId, PDO::PARAM_INT);
      $preparedSql->bindValue(":id",    (int)$itemId,  PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        $this->lastErrorString = $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2];
      }
    }

    return $ret;
  }


  public function modifyItemState($itemId, $stateId)
  {
    $ret = FALSE;

    if($this->dbh != NULL && is_numeric($itemId) && is_numeric($stateId))
    {
      $preparedSql = $this->dbh->prepare("UPDATE item SET state = :state WHERE id = :id");
      $preparedSql->bindValue(":state", (int)$stateId, PDO::PARAM_INT);
      $preparedSql->bindValue(":id",    (int)$itemId,  PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        $this->lastErrorString = $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2];
      }
    }

    return $ret;
  }


  public function modifyItemMemo($itemId, $memo)
  {
    $ret = FALSE;
    $memo = BookStockerDB::makeStringParameter($memo);

    if($this->dbh != NULL && is_numeric($itemId))
    {
      $preparedSql = $this->dbh->prepare("UPDATE item SET memo = :memo WHERE id = :id");
      if($memo === NULL)
      {
        $preparedSql->bindValue(":memo", NULL, PDO::PARAM_NULL);
      }
      else
      {
        $preparedSql->bindValue(":memo", (String)$memo, PDO::PARAM_STR);
      }
      $preparedSql->bindValue(":id", (int)$itemId,  PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        $this->lastErrorString = $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2];
      }
    }

    return $ret;
  }



}


?>
