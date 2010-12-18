<?php
function actblue_update_page($pid) {
  $node = node_load(db_result(db_query("SELECT nid FROM {actblue_pages} WHERE pid='%s'", $pid)));
  $class=actblue_class();
  $actblue = $class->getPage($pid);

  cache_set('actblue_page_'.$pid, $actblue,'cache',time()+300);
  if (!$node->actblue['custom_title']) {
    $node->title = $page['name'];
  }
  if (!$node->actblue['custom_blurb']) {
    $node->body = $node->teaser = $page['blurb'];
  }
  $node->actblue = array (
    'raised_amount' => $actblue['raised_amount'],
    'raised_count' => $actblue['raised_count'],
    'last_update' => time()
  );

  node_save($node);

  $result = db_query("SELECT e.eid,n.nid,n.status FROM {actblue_entities} e INNER JOIN {node} n ON e.nid=n.nid WHERE e.pid='%s'", $pid);
  while ($row=db_fetch_object($result)) {
    $entity[$row->eid]=$row;
  }

  // Check for new entities;
  foreach ($actblue['entities'] as $eid=>$e) {
    if (!isset($entity[$eid])) {
      actblue_import_entity($eid,$pid);
    } elseif ($entity[$eid]->status==0) {
      // Maybe this candidate was readded and we already have them in the database
      $ent=node_load($entity[$eid]->nid);
      $ent->status=1;
      node_save($ent);
    }
  }

  //Now set status to 0 on any removed entities.
  foreach ($entity as $eid=>$e) {
    if (!isset($actblue['entities'][$eid]) && $e->status==1) {
      $ent=node_load($e->nid);
      $ent->status=0;
      node_save($ent);
    }
  }

  return $actblue;
}

function actblue_import_entity($eid,$pid) {
  global $user;
  static $pages;
  $class=actblue_class();
  if (!$pages[$pid]) {
    $data=cache_get('actblue_page_'.$pid);
    if (!$data->data){
      $set=$class->getPage($pid);
      cache_set('actblue_page_'.$pid, $set,'cache',time()+300);
    } else {
      $set=$data->data;
    }
    $pages[$pid]['actblue']=$set;
    $pages[$pid]['node']=node_load(db_result(db_query("SELECT nid FROM {actblue_pages} WHERE pid='%s'", $pid)));

  }
  $page = $pages[$pid];

  $nid = db_result(db_query("SELECT nid FROM {actblue_entities} WHERE eid=%d AND pid='%s'", $eid, $pid));
  if ($nid) {
    $node=node_load($nid);

  } else {
    $node = new stdClass();
    if (!$user->uid){
      $node->uid=1;
    } else {
      $node->uid=$user->uid;
    }
    $node->format = variable_get('actblue_default_format',0);
    if (module_exists('comment')){
      $node->comment = variable_get('comment_actblue_entity', COMMENT_NODE_READ_WRITE);
    }
  }

  $page = $pages[$pid];
  $data = $class->getEntity($eid);
  $actblue=$data;
  unset($actblue['races']);

  if (isset($page['node']->actblue['cycle']) && isset($data['races'][$page['node']->actblue['cycle']])) {
    $actblue=array_merge($actblue,array_pop($data['races'][$page['node']->actblue['cycle']]));
    list($y,$m,$d)=explode('-', $actblue['election_date']);
    $actblue['election_date']=mktime(12,0,0,$m,$d,$y);
  } elseif (count($data['races'])) {
    $actblue=array_merge($actblue,array_pop(array_pop($data['races'])));
    list($y,$m,$d)=explode('-', $actblue['election_date']);
    $actblue['election_date']=mktime(12,0,0,$m,$d,$y);
  }

  $actblue['position']=$page['actblue']['entities'][$eid]['position'];
  // On updates we don't want to overwrite our weights
  if (!$nid) {
    $actblue['weight']=$actblue['position'];
  }
  $actblue['raised_amount']=$page['actblue']['entities'][$eid]['raised_amount'];
  $actblue['raised_count']=$page['actblue']['entities'][$eid]['raised_count'];
  $actblue['pid']=$pid;
  $actblue['eid']=$eid;
  $actblue['last_update'] = time();
  $node->type='actblue_entity';
  $node->uid = $page['node']->uid;
  if (!$node->actblue['custom_title']) {
    $node->title = $page['actblue']['entities'][$eid]['display_name'];
  }
  if (!$node->actblue['custom_blurb']) {
    $node->body = $node->teaser =  $page['actblue']['entities'][$eid]['blurb'];
  }
  // Create our taxonomy if needed
  if (!$nid && $page['node']->actblue['tid']) {
    $parent = taxonomy_get_term($page['node']->actblue['tid']);
    $edit = array (
      'name'=>$page['actblue']['entities'][$eid]['display_name'],
      'vid' => $parent->vid,
      'parent' => $parent->tid
    );
    taxonomy_save_term($edit);
    $actblue['tid']=$edit['tid'];
  }
  $node->actblue = $actblue;
  $node->status = 1;
  node_save($node);
  return $node;
}
