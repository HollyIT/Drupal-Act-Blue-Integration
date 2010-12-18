<?php


function actblue_add_page(&$form_state,$page=null) {
  drupal_set_title('Import a new ActBlue page');

  $form['pid']=array(
    '#type'=>'textfield',
    '#field_prefix'=>'http://www.actblue.com/page/',
    '#title'=>'Act Blue URL',
    '#default_value'=>$page['pid'],
    '#description'=>'Enter the URL to the Act Blue campaign page',
    '#required'=>!$edit,
    '#disabled'=>$edit
  );
  $form['is_active']=array(
    '#type'=>'radios',
    '#title' => 'Is Active',
    '#options' => array(0=>'No',1=>'Yes'),
    '#description' => 'If a page is set to active then information will automatically be updated from ActBlue',
    '#default_value' => isset($edit['is_active'])?$edit['is_active']:1
  );

  $form['cycle']=array(
    '#type'=>'textfield',
    '#title'=>'Year',
    '#description'=>'Enter the year that the elections on this fundraiser page is for',
    '#default_value'=>date('Y')
  );
  if (module_exists('taxonomy')) {
    $v = taxonomy_get_vocabularies();
    $vocs[0]='--NONE--';
    foreach ($v as $tid=>$data) {
      $vocs[$tid]=$data->name;
    }
    $form['tax']=array(
      '#type'=>'fieldset',
      '#title'=>'Auto Taxonomy'
    );
    $form['tax']['vid']=array(
      '#type'=>'select',
      '#title'=>'Vocabulary To Import To',
      '#options'=>$vocs,
      '#description' => 'If a category style taxonomy is selected then categories will automatically be generated for this page and it\'s entities. The items will not be automatically placed into the appropriate term, however it is useful for linking other items to these new enteries'
    );
  }
  $form['buttons']['submit']=array(
    '#type'=>'submit',
    '#value'=>'Import',
    '#suffix'=>'<div style="font-weight:bold;font-style:italic">NOTE: Once clicked Act Blue is contacted to download updated information. This can slow down the time to submit this form. Just be patient.</div> '
  );
  return $form;
}


function actblue_add_page_validate(&$form,&$form_state) {
  $class=actblue_class();
  $page=$class->getPage($form_state['values']['pid']);
  if (!$page) {
    form_set_error('pid','Page could not be found on ActBlue');
  } else {
    $page['cycle']=$form_state['values']['cycle'];
    $form_state['storage']['page']=$page;
  }

}

function actblue_add_page_submit(&$form,&$form_state) {
  global $user;
  $page = $form_state['storage']['page'];
  $node = new stdClass();
  $node->type='actblue_page';
  $node->uid = $user->uid;
  $node->title = $page['name'];
  $node->actblue_url = $page['url'];
  $node->actblue_pid = $page['pid'];
  $node->format = variable_get('actblue_default_format',0);
  $node->body = $node->teaser = $page['blurb'];
  if (module_exists('comment')) {
    $node->comment = variable_get('comment_actblue_entity', COMMENT_NODE_READ_WRITE);
  }

  $edit = array ('tid'=>0);
  if ($form_state['values']['vid']) {
    $edit = array (
      'name'=>$page['name'],
      'vid' => $form_state['values']['vid']
    );
    taxonomy_save_term($edit);
  }
  $node->actblue = array (
    'url' => $page['url'],
    'pid' => $page['pid'],
    'tid' => $edit['tid'],
    'raised_amount' => $page['raised_amount'],
    'raised_count' => $page['raised_count'],
    'cycle'=>$form_state['values']['cycle'],
    'is_active' => $form_state['values']['is_active'],
  );
  $node->status = 0;

  node_save($node);

  $page['node']=$node;
  $form_state['redirect']='node/'.$node->nid.'/edit/';
  $form_state['storage']=null;
  $batch = array(
    'operations' => array(array('actblue_import_process', array($page))),
    'title' => t('Importing Entities'),
    'init_message' => t('Starting Entity Emport.'),
    'progress_message' => t('Processed @current out of @total.'),
    'error_message' => t('Reindexing has encountered an error.'),
    'file'=>drupal_get_path('module','actblue').'/actblue.admin.php',
    'destination'=>'node/'.$node->nid.'/edit/'
  );
  batch_set($batch);
}


function actblue_import_process($page,&$context) {
  if (!isset($context['sandbox']['progress'])) {
    $context['sandbox']['entities'] = $page['entities'];
    $context['sandbox']['pid'] = $page['pid'];
    $context['sandbox']['max'] = count($page['entities']);
    $context['sandbox']['page']=$page;
  }

  include_once('actblue.inc');
  if (count($context['sandbox']['entities'])) {
    $entity=array_pop($context['sandbox']['entities']);

    $data = actblue_import_entity($entity['eid'], $page['pid']);
    $context['sandbox']['progress']++;
    $context['message'] = t('Importing '.$data->actblue['display_name']);
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }

  }
}

function actblue_settings() {

  $options = drupal_map_assoc(array(0,1,5,10,15,30,45,60,90,120));
  $form['cron_settings']=array(
    '#type'=>'fieldset',
    '#title' => 'Automatic Update Settings',
    '#description' => '**NOTE: These settings rely upon a properly running cron job setup on your server.'

  );


  $form['cron_settings']['actblue_update_campaigns_interval']=array(
    '#title'=>'Update Campaigns Interval',
    '#description' => 'Update active campaigns every X minutes. 0 to disable.',
    '#type'=>'select',
    '#options'=>$options,
    '#default_value'=>variable_get('actblue_update_campaigns_interval', 0)
  );

  $form['cron_settings']['actblue_update_entities_interval']=array(
    '#title'=>'Update Entities Interval',
    '#description' => 'Update active entities every X minutes. 0 to disable.',
    '#type'=>'select',
    '#options'=>$options,
    '#default_value'=>variable_get('actblue_update_entities_interval', 0)
  );

  $options = drupal_map_assoc(array(0,1,2,3,4,5,6,7,8,9,10));
  $form['cron_settings']['actblue_update_entities_count']=array(
    '#title'=>'Update Entities Count',
    '#description' => 'Update how many active entities per cron run. 0 to update all.',
    '#type'=>'select',
    '#options'=>$options,
    '#default_value'=>variable_get('actblue_update_entities_count', 0)
  );
  $formats = filter_formats();
  foreach ($formats as $fid=>$d){
    $format[$fid]=$d->name;
  }

  $form['actblue_default_format']=array(
    '#type'=>'select',
    '#title' => 'Default Input Format For Imported Items',
    '#description' => 'ActBlue items contain little HTML so you can select a less liberal input filter',
    '#default_value'=> variable_get('actblue_default_format',0),
    '#options' => $format

  );
  return system_settings_form($form);
}