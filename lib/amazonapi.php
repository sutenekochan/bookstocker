<?php

// ========== Amazon (AWS) Product Advertising API を管理するクラス ==========
//
// SubscriptionId (旧仕様) には非対応です。
// AmazonのAPIは最初の10個のみ返します。11個目以降は非対応 (対応する必要を感じていないので未実装)
//
// 使用法 (引数等の情報は各APIの説明を参照のこと)
//
//   ・初期化
//     $api = new AmazonApi('ABCDEFGHIJKLMNOPQUST', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890' 'abcdefgh-22', 'cache');
//       - 第1引数：Amazon Product Advertising API へのアクセスキー (必須)
//       - 第2引数：Amazon Product Advertising API へのシークレットキー (必須)
//       - 第3引数：Amazon Associate ID (任意。デフォルト：なし)
//       - 第4引数：キャッシュディレクトリ名 (任意。デフォルト：'cache')
//       - 注意   ：引数の形式はチェックしていない(そもそもアクセスキーの形式がどのような物か不明)。不正な値でも動くがAmazonから怒られるはず
//
//   ・キャッシュとURLとの両方を検索
//     $itemList1 = $api->searchByIsbn('1234567890');       // 10桁でも13桁でもok
//     $itemList2 = $api->searchByAsin('1234567890');       // Amazonの商品コード。書籍に関しては10桁ISBNと一致するっぽいのでISBNを入れてもok
//
//   ・オンライン検索
//     $itemList3 = $api->searchNetByIsbn('1234567890');    // 10桁でも13桁でもok
//     $itemList4 = $api->searchNetByAsin('1234567890');    // Amazonの商品コード。なお searchByAsin はキャッシュとネットと両方探す
//     $itemList5 = $api->searchByJan ('1234567890');       // JANコード
//     $itemList6 = $api->searchByKeywords('あいうえお');   // キーワード検索(UTF-8で指定)。A and B のような指定も可能
//       - 得られるのはclass AmazonItemList
//       - Amazonへのアクセスでエラーになった時にはNULLが返る
//       - 検索結果が0件の場合はAmazonItemListは作られるが、getNumber が0になる
//       - 該当商品が複数ある場合がある。レスポンスで帰ってくるのは最初の10個 (Amazon側の制限)
//       - isbn, jan, asin の検索では、キャッシュに書き込む
//
//     $api->getLastError();                                // NULLが返ってきた時にエラー文字列が返る
//
//   ・キャッシュ検索
//     $itemList7 = $api->searchCacheByAsin(1234567890');
//       - キャッシュ内でアイテムを検索。
//       - キャッシュが存在しない場合にはNULLになる。自動でamazonにアクセスしには行かない
//
// ・サポート関数
//     $isbn10 = AmazonApi::isbn13toIsbn10($isbn13);        // ISBN13をISBN10に計算 (check digitが再計算される)
//     $isbn10 = AmazonApi::calcIsbn10CheckDigit($isbn10);  // check digit の再計算
//     $isbn13 = AmazonApi::calcIsbn13CheckDigit($isbn13);
//     $asin = AmazonApi::getAsinFromUrl($amazonUrl);       // AmazonのURLからASINぽい文字列を得る。見つからない場合はNULLが帰る。
//                                                          // 対応しているのは長い形式のみで、シェア用の短い形式は非対応
//
//
//
//   ・XMLからアイテムリストを製作 (class AmazonItemList)
//     $itemList8 = new AmazonItemList();
//     $itemList7->parseXml($xmlString);
//       - 上記「検索」動作、または、キャッシュしたXMLをコンストラクタに渡すことで作られる
//       - XML Parse Error の時は Exception が起こる
//
//   ・Amazonがエラーを返したときにエラー情報を得る (XML的には正しいこと)
//    print $itemlist1->getErrorMessage();
//
//   ・アイテム情報取得
//     print $itemlist1->getAccessDate();                // XMLに記載されているアクセス日。XMLに記載されている通りの形式で「2012-03-04T05:06:07Z」という形式。GMT
//     $numberOfItems = $itemList1->getNumber();
//     print $itemlist1->getTitle();                     // 1番目の検索結果の情報を取得
//     print $itemlist1->getAuthor(2);                   // 2番目以降を指定する場合は引数を指定する。指定できるのは0～9。検索結果が10個以上ある場合でも11個目以降は取れない
//     print $itemlist1->getBinding();                   // 本の形状(新書等)
//     print $itemlist1->getAsin();
//     print $itemlist1->getIsbn();
//     print $itemlist1->getDetailPageUrl();             // Amazonの商品ページ
//     print $itemlist1->getAddToWishlistLink();
//     print $itemlist1->getMediumImageUrl();            // 画像のURL。Medium Size、160x100程度
//     print $itemlist1->getMediumImageHeight();
//     print $itemlist1->getMediumImageWidth();
//     print $itemlist1->getPublisher();                 // 出版社
//     print $itemlist1->getNumberOfPages();
//     print $itemlist1->getPublicationDate();
//     print $itemlist1->getLowestNewPrice();            // 新品価格で最安のもの。戻り値は数値ではなく「￥ 123」みたいな形式
//
//
// 作成の際に参考にしたURL
//   公式トップ  https://affiliate.amazon.co.jp/gp/advertising/api/detail/main.html
//   公式プログラミングガイド  https://images-na.ssl-images-amazon.com/images/G/09/associates/paapi/dg/index.html
//   公式のscratchpad  http://webservices.amazon.co.jp/scratchpad/index.html
//   Amazon Web サービス入門(Product Advertising API)  https://www.ajaxtower.jp/ecs/
//   PHPからAmazon Web ServiceのREST APIを利用するサンプル  http://blog.codebook-10000.com/entry/20131112/1384191896




// ---------- ここからclass AmazonApi ----------

class AmazonApi
{
  private $accessKey;
  private $secretKey;
  private $associateId;
  private $cacheDir;

  private $serviceName    = 'AWSECommerceService';
  private $serviceVersion = '2010-09-01';
  private $searchTarget   = 'Medium,Similarities';    // 検索対象
  private $searchIndex    = 'All';                    // 検索対象。'All' または 'Books'

  private $entryPointMethod = 'GET';
  private $entryPointScheme = 'http';
  //private $entryPointHost   = 'webservices.amazon.com';
  private $entryPointHost   = 'ecs.amazonaws.jp';
  private $entryPointPath   = '/onca/xml';

  private $lastErrorString;


  // ---------- コンストラクタ。引数必須 ----------
  public function __construct($accessKey, $secretKey, $associateId = '', $cacheDir = 'cache')
  {
    $this->accessKey   = $accessKey;
    $this->secretKey   = $secretKey;
    $this->associateId = $associateId;
    $this->cacheDir    = $cacheDir;
  }



  // ---------- 13桁ISBNから10桁ISBNへの変換 ----------
  // 引数が13桁の場合は10桁に変換して返し、10桁の場合はそのまま返す。checkDigitは再計算される。
  public static function isbn13toIsbn10($isbn13)
  {
    $isbn10 = $isbn13;
    if(strlen($isbn13) == 13)
    {
      $isbn10 = substr($isbn13, 3);
    }
    return AmazonApi::calcIsbn10CheckDigit($isbn10);
  }


  // ---------- ISBNのcheck digit(最後の桁)の再計算 ----------
  // 引数としてISBNを取り(間違ったCheck Digitを含む10/13桁)、正しいCheck DigitをつけたISBNを返す
  public static function calcIsbn10CheckDigit($isbn10)
  {
    $checkDigit = 0;
    for($i=0; $i<9; $i++)
    {
      $x = substr($isbn10, $i, 1);
      $checkDigit += $x * (10-$i);
    }
    $checkDigit = $checkDigit % 11;
    $checkDigit = 11 - $checkDigit;

    return (int)(substr($isbn10, 0, -1) . $checkDigit);
  }

  public static function calcIsbn13CheckDigit($isbn13)
  {
    $checkDigit = 0;
    for($i=0; $i<6; $i++)
    {
      $x1 = substr($isbn13, $i*2, 1);
      $x2 = substr($isbn13, $i*2+1, 1);
      $checkDigit += $x1 + $x2 * 3;
    }
    $checkDigit = $checkDigit % 10;
    $checkDigit = 10 - $checkDigit;

    return (int)(substr($isbn13, 0, -1) . $checkDigit);
    

  }


  // ---------- AmazonのURLからASINぽい文字列を得る ----------
  public static function getAsinFromUrl($amazonUrl)
  {
    $asin = NULL;

    if(is_string($amazonUrl))
    {

      $pos = strpos($amazonUrl, "/dp/");    // URLに /dp/ が含まれる場合、その後の / で囲まれた部分がASIN
      if($pos !== FALSE)
      {
        $asin = substr($amazonUrl, $pos+4);
      }
      else
      {
        $pos = strpos($amazonUrl, "/product/d/");  // URLに /product/d/ が含まれる場合、その後の / で囲まれた部分がASIN
        if($pos !== FALSE)
        {
          $asin = substr($amazonUrl, $pos+11);
        }
        else
        {
          $pos = strpos($amazonUrl, "/product/");  // URLに /product/ が含まれる場合(上記の /product/d/ を除く)、その後の / で囲まれた部分がASIN
          if($pos !== FALSE)
          {
            $asin = substr($amazonUrl, $pos+9);
          }
        }
      }

      if($asin !== NULL)
      {
        $pos = strpos($asin, "/");
        if($pos !== FALSE)
        {
          $asin = substr($asin, 0, $pos);
        }
      }
    }

    return $asin;
  }



  // ---------- ASINから商品を検索 ----------
  public function searchByIsbn($isbn)
  {
    $isbn10 = AmazonApi::isbn13toIsbn10($isbn);
    $itemList = $this->searchCacheByAsin($isbn10);    // ASINとISBNが現状一致していることで、こういう記載になっている。将来にわたって一致する保証はない
    if($itemList === NULL)
    {
      $itemList = $this->searchNetByIsbn($isbn10);
    }
    return $itemList;
  }

  public function searchByAsin($asin)
  {
    $itemList = $this->searchCacheByAsin($asin);
    if($itemList === NULL)
    {
      $itemList = $this->searchNetByAsin($asin);
    }
    return $itemList;
  }



  // ---------- ISBN/ASINから商品をオンライン検索 ----------
  public function searchNetByIsbn($isbn)
  {
    $isbn10 = AmazonApi::isbn13toIsbn10($isbn);
    return $this->searchById([
      'IdType'      => 'ISBN',
      'ItemId'      => $isbn10,
      'SearchIndex' => $this->searchIndex,
    ]);
  }

  public function searchNetByAsin($asin)
  {
    return $this->searchById([
      'IdType'      =>'ASIN',
      'ItemId'      =>$asin,
    ]);
  }

  public function searchByJan($jan)
  {
    return $this->searchById([
      'IdType'      => 'JAN',
      'ItemId'      => $jan,
      'SearchIndex' => $this->searchIndex,
    ]);
  }

  private function searchById($params)
  {
    $allParams = [
      'Operation'     => 'ItemLookup',
      //'MerchantId'    => 'All',          // Amazon以外から出品されているものも検索対象に含める場合は指定
      //'Condition'     => 'All',          // 中古品も含めて検索する場合は 'All' を指定。デフォルトは新品 ('New')
      'ResponseGroup'   => $this->searchTarget,
    ];
    $allParams = array_merge($allParams, $params);
    $url = $this->makeUrl($allParams);

    $responceText = $this->request($url);

    $itemList = NULL;
    if($responceText)
    {
      $itemList = new AmazonItemList();
      $itemList->parseXml($responceText);
      if($itemList->getAsin() == '')
      {
        $errorMessage = $itemList->getErrorMessage();
        if($errorMessage != "")
        {
          if($this->lastErrorString == "")
          {
            $this->lastErrorString = $errorMessage;
          } else {
            $this->lastErrorString .= "\n" . $errorMessage;
          }
        }
        $itemList = NULL;
      }
      else
      {
        $this->saveToCache($itemList->getAsin(), $responceText);
      }
    }

    return $itemList;
  }


  // ---------- キーワードで商品を検索 ----------
  public function searchByKeywords($keywords)
  {
    $allParams = [
      'Operation'     => 'ItemSearch',
      'Keywords'      => $keywords,
      'SearchIndex'   => $this->searchIndex,
      //'MerchantId'    => 'All',          // Amazon以外から出品されているものも検索対象に含める場合は指定
      //'Condition'     => 'All',          // 中古品も含めて検索する場合は 'All' を指定。デフォルトは新品 ('New')
      'ResponseGroup' => $this->searchTarget,
    ];
    $url = $this->makeUrl($allParams);

    $responceText = $this->request($url);

    $itemList = NULL;
    if($responceText)
    {
      $itemList = new AmazonItemList();
      $itemList->parseXml($responceText);
      // 複数商品が含まれている可能性があるので、キャッシュ保存は行わない
      if($itemList->getAsin() == '')
      {
        $errorMessage = $itemList->getErrorMessage();
        if($errorMessage != "")
        {
          if($this->lastErrorString == "")
          {
            $this->lastErrorString = $errorMessage;
          } else {
            $this->lastErrorString .= "\n" . $errorMessage;
          }
        }
        $itemList = NULL;
      }
    }
    return $itemList;
  }


  // ---------- キャッシュ内で商品を検索 ----------
  public function searchCacheByAsin($asin)
  {
    $filename = $this->cacheDir . '/' . $asin . '.xml';

    $itemList = NULL;

    if(file_exists($filename))
    {
      $contents = file_get_contents($filename);
      if($contents)
      {
        $itemList = new AmazonItemList();
        $itemList->parseXml($contents);
      }
    }

    return $itemList;
  }



  // ---------- 内部関数：URL製作 ----------
  // $urlString = makeUrl($params);
  // 第1引数：配列。Operation毎の固有パラメータ
  // 戻り値 ：文字列。リクエストに使うURL
  // 注意   ：この関数が呼ばれた時点での timestamp と署名を含めるため、すぐに値を使うこと。
  private function makeUrl($params)
  {
    // パラメータ製作
    $allParams = $this->makeUrlParams($params);

    // パラメータを結合して文字列を作る
    $queryString = '';
    foreach ($allParams as $paramKey => $paramValue)
    {
      $queryString .= '&' . $paramKey . "=" . $paramValue;
    }
    $queryString = substr($queryString, 1);

    // 署名を作成
    $stringToSign  = $this->entryPointMethod . "\n" . $this->entryPointHost . "\n" . $this->entryPointPath . "\n" . $queryString;
    $sign = $this->urlencode_rfc3986(base64_encode(hash_hmac('sha256', $stringToSign, $this->secretKey, true)));


    // 最終的なURLを作成
    $url = $this->entryPointScheme . "://" .  $this->entryPointHost . $this->entryPointPath . "?" . $queryString . "&Signature=" . $sign;

    //print "[debug] URL: " . $url . "\n";

    return $url;
  }



  // ---------- 内部関数：URL内のパラメータ製作 ----------
  // $allParams = makeUrlParams($params);
  // 第1引数：配列。Operation毎の固有パラメータ
  // 戻り値 ：配列。共通パラメータを含めた全パラメータ。正規化済
  // 注意   ：この関数が呼ばれた時点での timestamp を含めるため、すぐに値を使うこと。
  private function makeUrlParams($params)
  {
    $allParams = [
      'Service'        => $this->serviceName,
      'AWSAccessKeyId' => $this->accessKey,
      'Version'        => $this->serviceVersion,
    ];

    if($this->associateId !== '')
    {
      $allParams = array_merge($allParams, ['AssociateTag' => $this->associateId]);
    }

    $allParams = array_merge($allParams, $params);

    // Debug用パラメータ。Amazonサーバ側をValidation Modeにする。実運用では使用しない
    //$allParams = array_merge($allParams, ['Validate' => 'True']);

    // パラメータにtimestamp付与
    $allParams = array_merge($allParams, ['Timestamp' => gmdate('Y-m-d\TH:i:s\Z')]);


    // パラメータの正規化
    $normalizedParams = [];
    foreach ($allParams as $paramName => $paramValue)
    {
      $normalizedParams[$this->urlencode_rfc3986($paramName)] = $this->urlencode_rfc3986($paramValue);
    }
    ksort($normalizedParams);


    return $normalizedParams;
  }


  // ---------- 内部関数：URLencode ----------
  // PHP5.3.0以前では rawurlencode() で ~(チルダ) もエンコードしていたため、それを戻して RFC3986 に厳密に対応させる。
  // PHP5.4.0以降では rawurlencode() を直接使ってもかまわない
  private function urlencode_rfc3986($string)
  {
    $encodedString = rawurlencode($string);
    $encodedString = str_replace('%7E', '~', $encodedString);
    return $encodedString;
  }


  // ---------- 内部関数：URLにアクセス ----------
  // 戻り値：responce text
  private function request($url)
  {
    //print "[debug] request url: " . $url . "\n\n";

    $context = stream_context_create(array( 'http' => array('ignore_errors' => true) ));  // 400番台や500番台のstatus codeが帰ってきてもコンテンツを取得
    $responceText = @file_get_contents($url, FALSE, $context);    // いちおうエラー表示は抑制しておく、debug時は外すこと
    //file_put_contents('cache/debug.txt', implode("\n", $http_response_header) . "\n\n" . $responceText);  // debug用。GETしたコンテンツをファイルに保存する

    if(count($http_response_header) <= 0)
    {
      $this->lastErrorString = 'Server does not responce any text.';
      $responceText = NULL;  // 念のため
    }
    else
    {
      $responceStatusArray = explode(' ', $http_response_header[0], 3);
      $responceStatusCode = $responceStatusArray[1];  // 404等のコード
      $responceStatusMessage = $responceStatusArray[2];  // Not found 等の文字列

      // 正常終了、あるいは、エラーコード＋エラーの内容を示すコンテンツ
      switch($responceStatusCode)
      {
        case 200:
          $this->lastErrorString = '';
          break;
        default:  // 404,503 etc.  この場合でも responceText は not null の場合がある
          $this->lastErrorString = $responceStatusCode . " " . $responceStatusMessage;
          break;
      }
    }

    return $responceText;
  }


  // ---------- HTTPのエラー情報を返す ----------
  public function getLastError()
  {
    return $this->lastErrorString;
  }


  // ---------- 内部関数：キャッシュに保存 ----------
  public function saveToCache($asin, $xmlText)
  {
    if(file_exists($this->cacheDir))
    {
      $filename = $this->cacheDir . '/' . $asin . '.xml';

      if(file_exists($filename))
      {
        $oldfilename = $filename . '.old';
        if(file_exists($oldfilename))
        {
          unlink($oldfilename);
        }
        rename($filename, $oldfilename);
      }

      file_put_contents($filename, $xmlText);
    }
  }



}


// ---------- ここからclass AmazonItemList ----------

class AmazonItemList
{
  private $xmlString;    // 渡されたままの素のXML
  private $xmlData;      // SimpleXMLElementクラス


  // ---------- コンストラクタ ----------
  public function __construct()
  {
    $this->xmlString = '';
    $this->xmlData = false;
  }


  // ---------- 初期化 ----------
  // 第1引数：Amazonから返されたXML
  public function parseXml($xmlString)
  {
    $this->xmlString = $xmlString;

    libxml_use_internal_errors(true);
    $this->xmlData = new SimpleXMLElement($xmlString);
    if($this->xmlData === false) {
      throw (new Exception("XML処理失敗"));
    }
  }


  // ---------- いろいろなものを返す関数 ----------
  public function getErrorMessage()
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if(isset($this->xmlData->{'Error'}))
      {
        $value = $this->xmlData->{'Error'}->{'Code'};
        $value .= " : ";
        $value .= $this->xmlData->{'Error'}->{'Message'};
      }
      else if(isset($this->xmlData->{'Items'}->{'Request'}->{'Errors'}))
      {
        $value = $this->xmlData->{'Items'}->{'Request'}->{'Errors'}->{'Error'}->{'Code'};
        $value .= " : ";
        $value .= $this->xmlData->{'Items'}->{'Request'}->{'Errors'}->{'Error'}->{'Message'};
      }
    }
    return $value;
  }

  public function getAccessDate()
  {
    $value = '';
    if($this->xmlData !== false)
    {
      foreach ($this->xmlData->{'OperationRequest'}->{'Arguments'}->{'Argument'} as $argument)
      {
        if($argument['Name'] == 'Timestamp')
        {
          $value = $argument['Value'];
        }
      }
    }
    return $value;
  }

  public function getNumber()
  {
    $num = 0;
    if($this->xmlData !== false)
    {
      if(isset($this->xmlData->{'Items'}->{'TotalResults'})) // 複数のアイテムがある場合
      {
        $num = $this->xmlData->{'Items'}->{'TotalResults'};
      }
      else if(!isset($this->xmlData->{'Items'}->{'Request'}->{'Errors'})) // 1個のアイテム、かつ、エラーでない場合
      {
        $num = 1;
      }
    }
    return $num;
  }


  public function getAsin($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        $value = @($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'ASIN'});
      }
    }
    return $value;
  }


  public function getIsbn($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        $value = @($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'ItemAttributes'}->{'ISBN'});
      }
    }
    return $value;
  }


  public function getTitle($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        $value = @($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'ItemAttributes'}->{'Title'});
      }
    }
    return $value;
  }


  public function getAuthor($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        $value = @($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'ItemAttributes'}->{'Author'});
      }
    }
    return $value;
  }


  public function getBinding($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        $value = @($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'ItemAttributes'}->{'Binding'});
      }
    }
    return $value;
  }


  public function getDetailPageUrl($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        $value = @($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'DetailPageURL'});
      }
    }
    return $value;
  }


  public function getAddToWishlistLink($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        foreach ($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'ItemLinks'}->{'ItemLink'} as $itemLink)
        {
          if($itemLink->{'Description'} == 'Add To Wishlist')
          {
            $value = $itemLink->{'URL'};
          }
        }
      }
    }
    return $value;
  }


  public function getMediumImageUrl($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        $value = @($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'MediumImage'}->{'URL'});
      }
    }
    return $value;
  }


  public function getMediumImageHeight($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        $value = @($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'MediumImage'}->{'Height'});
      }
    }
    return $value;
  }


  public function getMediumImageWidth($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        $value = @($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'MediumImage'}->{'Width'});
      }
    }
    return $value;
  }


  public function getPublisher($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        $value = @($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'ItemAttributes'}->{'Publisher'});
      }
    }
    return $value;
  }


  public function getNumberOfPages($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        $value = @($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'ItemAttributes'}->{'NumberOfPages'});
      }
    }
    return $value;
  }


  public function getPublicationDate($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        $value = @($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'ItemAttributes'}->{'PublicationDate'});
      }
    }
    return $value;
  }



  public function getLowestNewPrice($index = 1)
  {
    $value = '';
    if($this->xmlData !== false)
    {
      if($this->getNumber() >= $index)
      {
        $value = @($this->xmlData->{'Items'}->{'Item'}[$index - 1]->{'OfferSummary'}->{'LowestNewPrice'}->{'FormattedPrice'});
      }
    }
    return $value;
  }


}


?>
