<?php

class ActBlue {

  public $timeout=20;
  private $method='GET';
  private $post=null;
  private $apiVersion="2009-08";
  public $lastError="";
  public $xml;

  public function __construct() {

  }

  public function getPage($page) {
    if (strstr("/",$page)) {
      $page=array_pop(explode("/",$page));
    }
    if (file_exists($page.'.xml')) {

      $xml=$this->loadLocal($page);
    } else {
      $xml=$this->connect("pages/$page");
    }
    if (!$xml) {
      return false;
    }

    $this->xml = $xml;

    $campaign=array(
      'pid'=>$page,
      'name'=>(string) $xml->title,
      'url' => 'http://actblue.com/page/'.$page,
      'blurb'=> (string) $xml->blurb

    );

    foreach ($xml->scoreboards->scoreboard as $sb) {
      if ($sb->attributes()=='page' && $sb->fact->attributes()==$page) {
        $campaign['raised_amount']=(string) $sb->fact->total;
        $campaign['raised_count']=(string) $sb->fact->count;


      } else {
        foreach ($sb->fact as $en) {

          $id=(int) $en->attributes()->entity;
          if ((string)$en->attributes()->page==$page) {
            $track[$id]=array(
              'eid'=>$id,
              'raised_amount'=>(string) $en->total,
              'raised_count'=> (string) $en->count,
              'display_name' => (string) $en->entity->displayname,
              'legal_name' =>(string) $en->entity->legalname

            );
          }
        }
      }
    }

    foreach ($xml->listentries->listentry as $le) {

      $id=(int) (string) $le->entity->attributes()->id;
      if ($track[$id]) {
        $entity[$id] = $track[$id];
      } else {
        // Entity is newly added and the fact section isn't populated yet
        $entity[$id]=array(
          'eid'=>$id,
          'raised_amount'=>(string) 0,
          'raised_count'=> (string) 0,
          'display_name' => (string) $le->entity->displayname,
          'legal_name' =>(string) $le->entity->legalname

        );
      }
      $entity[$id]['position'] = (int)  $le->attributes()->position;
      $entity[$id]['blurb'] = (string) $le->blurb;
    }

    $campaign['entities']=$entity;

    return $campaign;
  }

  public function getEntity($entity) {
    if (file_exists($entity.'.xml')) {
      $xml=$this->loadLocal($entity);

    } else {
      $xml=$this->connect("entities/$entity");
    }
    if (!$xml) {
      return false;
    }
    $this->xml = $xml;
    $entity=array(
      'eid' => (int) $xml->attributes(),
      'legal_name'=> (string) $xml->legalname,
      'display_name'=> (string) $xml->displayname,
      'sort_name' => (string) $xml->sortname,
      'image' => (string) $xml->links->image,
      'website' => (string) $xml->links->website,

    );
    if ($xml->candidacies->candidacy) {
      foreach ($xml->candidacies->candidacy as $c) {

        $date = (string) $c->election->general_date;
        list($cycle,$m,$d)=explode("-",$date);
        $cand=array(
          'cycle'=>$cycle,
          'election_date'=>$date,
          'race_name'=> (string) $c->election->office->name,
          'race_type'=> (string) $c->election->office->race_type,
          'district'=> (string) $c->election->office->district,
          'state'=> (string) $c->election->office->state,
          'result' => (string) $c->result
        );
        $races[$cycle][]=$cand;

      }
    }

    $entity['races']=$races;
    return $entity;
  }

  private function loadLocal($file) {
    $fp=fopen("$file.xml",'r');
    $html=fread($fp,filesize("$file.xml"));
    fclose($fp);
    $xml=$this->html2xml($html);
    if (!$xml) {
      $this->lastError='XML Parsing Error Occured';
      return false;
    }
    return $xml;
  }
  private function connect($page,$params=array()) {
    $par=array();
    if (count($params)) {
      foreach ($params as $key=>$value) {
        $par[]="$key=".urlencode($value);
      }

    }
    $url="https://secure.actblue.com/api/".$this->apiVersion.'/'.$page;

    if (count($par)) {
      $url.='?'.implode("&",$par);
    }
    $header [ 0 ] = "Accept: application/xml" ;
    $curl=curl_init();
    curl_setopt ( $curl, CURLOPT_URL, $url ) ;
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt ($curl, curlOPT_SSL_VERIFYHOST, false);
    curl_setopt ( $curl, CURLOPT_HTTPHEADER, $header ) ;
    curl_setopt ( $curl, CURLOPT_REFERER, 'http://'.$_SERVER['SERVER_NAME'] ) ;
    curl_setopt ( $curl, CURLOPT_AUTOREFERER, false ) ;
    curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 ) ;
    curl_setopt ( $curl, CURLOPT_TIMEOUT, 20 ) ;
    $html = curl_exec ( $curl ) ; // execute the curl command

    if (!$html) {
      $this->lastError=curl_error($curl);
      curl_close ( $curl ) ;
      return false;
    }
    curl_close ( $curl ) ; // close the connection

    $xml=$this->html2xml($html);
    if (!$xml) {
      $this->lastError='XML Parsing Error Occured';
      return false;
    }

    return $xml;
  }

  private function html2xml($html) {
    if (preg_match('/(<\?(.*?)>)/',$html,$matches)) {
      $html=$matches[0].str_replace($matches[0],'',$html);
    }
    $xml = simplexml_load_string ( $html ) ;
    if ($xml) {
      return $xml;
    }

    return false;
  }
}
