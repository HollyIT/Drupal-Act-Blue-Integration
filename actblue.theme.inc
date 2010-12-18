<?php



function theme_actblue_entity_image($entity,$teaser,$page) {

  $output.= '<img src="'.$entity->actblue['image'].'" alt="'.$entity->actblue['display_name'].'" class="actblue-entity-image" />';

  return $output;

}
theme_hidden($element);

function theme_actblue_form_open($node,$teaser,$page) {
  $output='<form action="https://secure.actblue.com/donate" method="post">'."\n";
  $output.='<input type="hidden" name="list" value="'.$node->actblue['pid'].'" />';
  $output.='<input type="hidden" name="referrer" value="http://www.actblue.com" />'."\n";
  if ($node->type=='actblue_page' && !$teaser) {
    $output.='<div class="actblue-donate-page"><input type="submit" name="submit" class="actblue-top-button" value="Submit">Donate: $<input type="text" name="amount" size="10" value="0.00"/><span>(amount distributed equally among all candidates)</span></div>';
  }
  return $output;
}

function theme_actblue_form_close($node,$teaser,$page) {
  if (!$teaser) {
    return '<div id="donation-footer"><input type="submit" name="submit" class="actblue-bottom-button" value="Submit"><div class="actblue-donate-total">Your Total Donation <span id="totaldonation">$0.00</span></div></div></form>';
  } else {
    return '<div class="actblue-donate-page"><input type="submit" name="submit" class="actblue-top-button" value="Submit">Donate: $<input type="text" name="amount" size="10" value="0.00"/><span>(amount distributed equally among all candidates)</span></div></form>';
  }

}

function theme_actblue_form_item($node,$teaser,$page) {
  return '<div class="act-blue-item-donate">Donate: $<input class="contribution-amount" name="recipients['.$node->actblue['eid'].']" type="text" size="10" value="0.00"/></div>';
}

function theme_actblue_totals($node,$teaser,$page) {
  $output='<div class="actblue-totals">';
  if (!$teaser && $page) {
    if ($node->actblue['page']) {
            $output.=$node->actblue['page']->title;
      $ex=' for '.$node->title;
    } else {
      $output.=$node->title;
      $ex=' for '.$node->title;
    }
  } else {

    $output.=$node->title;
  }
  $output.=' has raised <span>$'.$node->actblue['raised_amount'].'</span> from <span>'.$node->actblue['raised_count'].'</span> '.format_plural($node->actblue['raised_count'],'donor','donors').$ex.'.</div>';
  return $output;
}