<?php

/**
 * @file
 * Contains \Drupal\esim_research_migration\Form\EsimResearchMigrationAbstractBulkApprovalForm.
 */

namespace Drupal\esim_research_migration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;


class EsimResearchMigrationAbstractBulkApprovalForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'esim_research_migration_abstract_bulk_approval_form';
  }



public function buildForm(array $form, FormStateInterface $form_state) {
  $options_first = _bulk_list_of_research_migration_project();
  $selected = $form_state->getValue('research_migration_project') ?? key($options_first);

  $form['research_migration_project'] = [
    '#type' => 'select',
    '#title' => $this->t('Title of the Research Migration project'),
    '#options' => $options_first,
    '#default_value' => $selected,
    '#ajax' => [
      'callback' => [$this, 'ajaxBulkResearchMigrationAbstractDetailsCallback'],
      'event' => 'change',
      'wrapper' => 'ajax_selected_research_migration_wrapper',
    ],
    '#suffix' => '<div id="ajax_selected_research_migration_wrapper"><div id="ajax_selected_research_migration"></div><div id="ajax_selected_research_migration_pdf"></div></div>',
  ];
// var_dump(_research_migration_details(10));die;
  $form['research_migration_actions'] = [
    '#type' => 'select',
    '#title' => $this->t('Please select action for Research Migration project'),
    '#options' => _bulk_list_research_migration_actions(),
    '#default_value' => 0,
    '#prefix' => '<div id="ajax_selected_research_migration_action" style="color:red;">',
    '#suffix' => '</div>',
    '#states' => [
      'invisible' => [
        ':input[name="research_migration_project"]' => ['value' => 0],
      ],
    ],
  ];

  $form['message'] = [
    '#type' => 'textarea',
    '#title' => $this->t('If Dis-Approved please specify reason for Dis-Approval'),
    '#prefix' => '<div id="message_submit">',
    '#states' => [
      'visible' => [
        [
          ':input[name="research_migration_actions"]' => ['value' => 2],
        ],
        'or',
        [
          ':input[name="research_migration_actions"]' => ['value' => 3],
        ],
      ],
    ],
  ];

  $form['submit'] = [
    '#type' => 'submit',
    '#value' => $this->t('Submit'),
    '#states' => [
      'visible' => [
        ':input[name="research_migration_actions"]' => ['!value' => 0],
      ],
    ],
  ];

  return $form;
}

/**
 * AJAX callback for project selection.
 */
public function ajaxBulkResearchMigrationAbstractDetailsCallback(array &$form, FormStateInterface $form_state) {
  $response = new AjaxResponse();
  $selected_project = $form_state->getValue('research_migration_project');

  if ($selected_project != 0) {
    $details_markup = _research_migration_details($selected_project);

    $response->addCommand(new HtmlCommand('#ajax_selected_research_migration', $details_markup));

    // Refresh the options in the second select box
    $form['research_migration_actions']['#options'] = _bulk_list_research_migration_actions();
    $rendered_actions = \Drupal::service('renderer')->render($form['research_migration_actions']);
    $response->addCommand(new ReplaceCommand('#ajax_selected_research_migration_action', $rendered_actions));
  }
  else {
    $response->addCommand(new HtmlCommand('#ajax_selected_research_migration', ''));
  }

  return $response;
}

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    $msg = '';
    $root_path = esim_research_migration_path();
    //var_dump($root_path);die;
    if ($form_state->get(['clicked_button', '#value']) == 'Submit') {
      if ($form_state->getValue(['research_migration_project']))
        //var_dump($form_state['values']['research_migration_actions']);die;
        // research_migration_abstract_del_lab_pdf($form_state['values']['research_migration_project']);
 {
        if (\Drupal::currentUser()->hasPermission('Research Migration bulk manage abstract')) {
          $query = \Drupal::database()->select('research_migration_proposal');
          $query->fields('research_migration_proposal');
          $query->condition('id', $form_state->getValue(['research_migration_project']));
          $user_query = $query->execute();
          $user_info = $user_query->fetchObject();
          //var_dump($user_info);die;
          $user_data = \Drupal::entityTypeManager()->getStorage('user')->load($user_info->uid);
          if ($form_state->getValue(['research_migration_actions']) == 1) {
            // approving entire project //
            $query = \Drupal::database()->select('research_migration_submitted_abstracts');
            $query->fields('research_migration_submitted_abstracts');
            $query->condition('proposal_id', $form_state->getValue(['research_migration_project']));
            $abstracts_q = $query->execute();
            //var_dump($abstracts_q);die;
            $experiment_list = '';
            while ($abstract_data = $abstracts_q->fetchObject()) {
              \Drupal::database()->query("UPDATE {research_migration_submitted_abstracts} SET abstract_approval_status = 1, is_submitted = 1, approver_uid = :approver_uid WHERE id = :id", [
                ':approver_uid' => $user->uid,
                ':id' => $abstract_data->id,
              ]);
              \Drupal::database()->query("UPDATE {research_migration_submitted_abstracts_file} SET file_approval_status = 1, approvar_uid = :approver_uid WHERE submitted_abstract_id = :submitted_abstract_id", [
                ':approver_uid' => $user->uid,
                ':submitted_abstract_id' => $abstract_data->id,
              ]);
            } //$abstract_data = $abstracts_q->fetchObject()
            \Drupal::messenger()->addStatus(t('Approved Research Migration Project. Use the checkbox below to publish this Research Migration on the completed Research Migration page.'));
            drupal_goto('research-migration-project/manage-proposal/status/' . $form_state->getValue(['research_migration_project']));
            // email 
            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $email_subject = t('[!site_name][Research Migration Project] Your uploaded Research Migration project have been approved', array(
            // 						'!site_name' => variable_get('site_name', '')
            // 					));

            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $email_body = array(
            // 						0 => t('
            // 
            // Dear ' . $user_info->contributor_name . ',
            // 
            // Your uploaded project files for the Research Migration project has been approved.
            // 
            // Title of Research Migration project  : ' . $user_info->project_title . '
            // 
            // Best Wishes,
            // 
            // !site_name Team,
            // FOSSEE,IIT Bombay', array(
            // 							'!site_name' => variable_get('site_name', ''),
            // 							'!user_name' => $user_data->name
            // 						))
            // 					);

            /** sending email when everything done **/
            $email_to = $user_data->mail;
            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $from = variable_get('research_migration_from_email', '');

            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $bcc = variable_get('research_migration_emails', '');

            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $cc = variable_get('research_migration_cc_emails', '');

            $params['standard']['subject'] = $email_subject;
            $params['standard']['body'] = $email_body;
            $params['standard']['headers'] = [
              'From' => $from,
              'MIME-Version' => '1.0',
              'Content-Type' => 'text/plain; charset=UTF-8; format=flowed; delsp=yes',
              'Content-Transfer-Encoding' => '8Bit',
              'X-Mailer' => 'Drupal',
              'Cc' => $cc,
              'Bcc' => $bcc,
            ];
            if (!drupal_mail('research_migration', 'standard', $email_to, language_default(), $params, $from, TRUE)) {
              $msg = \Drupal::messenger()->addError('Error sending email message.');
            } //!drupal_mail('research_migration', 'standard', $email_to, language_default(), $params, $from, TRUE)
          } //$form_state['values']['research_migration_actions'] == 1
          elseif ($form_state->getValue(['research_migration_actions']) == 2) {
            //pending review entire project 
            $query = \Drupal::database()->select('research_migration_submitted_abstracts');
            $query->fields('research_migration_submitted_abstracts');
            $query->condition('proposal_id', $form_state->getValue(['research_migration_project']));
            $abstracts_q = $query->execute();
            $experiment_list = '';
            while ($abstract_data = $abstracts_q->fetchObject()) {
              \Drupal::database()->query("UPDATE {research_migration_submitted_abstracts} SET abstract_approval_status = 0, is_submitted = 0, approver_uid = :approver_uid WHERE id = :id", [
                ':approver_uid' => $user->uid,
                ':id' => $abstract_data->id,
              ]);
              \Drupal::database()->query("UPDATE {research_migration_proposal} SET is_submitted = 0, approver_uid = :approver_uid WHERE id = :id", [
                ':approver_uid' => $user->uid,
                ':id' => $abstract_data->proposal_id,
              ]);
              \Drupal::database()->query("UPDATE {research_migration_submitted_abstracts_file} SET file_approval_status = 0, approvar_uid = :approver_uid WHERE submitted_abstract_id = :submitted_abstract_id", [
                ':approver_uid' => $user->uid,
                ':submitted_abstract_id' => $abstract_data->id,
              ]);
            } //$abstract_data = $abstracts_q->fetchObject()
            \Drupal::messenger()->addStatus(t('The proposal has been marked for resubmission'));
            // email 
            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $email_subject = t('[!site_name][Research Migration Project] Your uploaded Research Migration project have been marked as pending', array(
            // 						'!site_name' => variable_get('site_name', '')
            // 					));

            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $email_body = array(
            // 						0 => t('
            // 
            // Dear ' . $user_info->contributor_name . ',
            // 
            // Kindly resubmit the project files for the project : ' . $user_info->project_title . '.
            // 
            // Reason for resubmission: ' . $form_state['values']['message'] . '
            // 
            // Best Wishes,
            // 
            // !site_name Team,
            // FOSSEE,IIT Bombay', array(
            // 							'!site_name' => variable_get('site_name', ''),
            // 							'!user_name' => $user_data->name
            // 						))
            // 					);

            /** sending email when everything done **/
            $email_to = $user_data->mail;
            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $from = variable_get('research_migration_from_email', '');

            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $bcc = variable_get('research_migration_emails', '');

            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $cc = variable_get('research_migration_cc_emails', '');

            $params['standard']['subject'] = $email_subject;
            $params['standard']['body'] = $email_body;
            $params['standard']['headers'] = [
              'From' => $from,
              'MIME-Version' => '1.0',
              'Content-Type' => 'text/plain; charset=UTF-8; format=flowed; delsp=yes',
              'Content-Transfer-Encoding' => '8Bit',
              'X-Mailer' => 'Drupal',
              'Cc' => $cc,
              'Bcc' => $bcc,
            ];
            if (!drupal_mail('research_migration', 'standard', $email_to, language_default(), $params, $from, TRUE)) {
              \Drupal::messenger()->addError('Error sending email message.');
            } //!drupal_mail('research_migration', 'standard', $email_to, language_default(), $params, $from, TRUE)
          } //$form_state['values']['research_migration_actions'] == 2
          elseif ($form_state->getValue(['research_migration_actions']) == 3) //disapprove and delete entire Research Migration project
 {
            if (strlen(trim($form_state->getValue(['message']))) <= 30) {
              $form_state->setErrorByName('message', t(''));
              $msg = \Drupal::messenger()->addError("Please mention the reason for disapproval. Minimum 30 character required");
              return $msg;
            } //strlen(trim($form_state['values']['message'])) <= 30
            if (!\Drupal::currentUser()->hasPermission('Research Migration bulk delete abstract')) {
              $msg = \Drupal::messenger()->addError(t('You do not have permission to Bulk Dis-Approved and Deleted Entire Lab.'));
              return $msg;
            } //!user_access('research_migration bulk delete code')
            if (research_migration_abstract_delete_project($form_state->getValue(['research_migration_project']))) //////
 {
              \Drupal::messenger()->addStatus(t('Dis-Approved and Deleted Entire Research Migration project.'));
              // @FIXME
              // // @FIXME
              // // This looks like another module's variable. You'll need to rewrite this call
              // // to ensure that it uses the correct configuration object.
              // $email_subject = t('[!site_name][Research Migration Project] Your uploaded Research Migration project have been marked as dis-approved', array(
              // 						'!site_name' => variable_get('site_name', '')
              // 					));

              // @FIXME
              // // @FIXME
              // // This looks like another module's variable. You'll need to rewrite this call
              // // to ensure that it uses the correct configuration object.
              // $email_body = array(
              // 						0 => t('
              // Dear ' . $user_info->contributor_name . ',
              // 
              // We regret to inform you that the project files submitted for the Research Migration project title: ' . $user_info->project_title . ' are disapproved by the reviewer.
              // 
              // Reason for dis-approval: ' . $form_state['values']['message'] . '
              // 
              // Best Wishes,
              // 
              // !site_name Team,
              // FOSSEE,IIT Bombay', array(
              // 						'!site_name' => variable_get('site_name', ''),
              // 						'!user_name' => $user_data->name
              // 											))
              // 					);

              $email_to = $user_data->mail;
              // @FIXME
              // // @FIXME
              // // This looks like another module's variable. You'll need to rewrite this call
              // // to ensure that it uses the correct configuration object.
              // $from = variable_get('research_migration_from_email', '');

              // @FIXME
              // // @FIXME
              // // This looks like another module's variable. You'll need to rewrite this call
              // // to ensure that it uses the correct configuration object.
              // $bcc = variable_get('research_migration_emails', '');

              // @FIXME
              // // @FIXME
              // // This looks like another module's variable. You'll need to rewrite this call
              // // to ensure that it uses the correct configuration object.
              // $cc = variable_get('research_migration_cc_emails', '');

              $params['standard']['subject'] = $email_subject;
              $params['standard']['body'] = $email_body;
              $params['standard']['headers'] = [
                'From' => $from,
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/plain; charset=UTF-8; format=flowed; delsp=yes',
                'Content-Transfer-Encoding' => '8Bit',
                'X-Mailer' => 'Drupal',
                'Cc' => $cc,
                'Bcc' => $bcc,
              ];
              if (!drupal_mail('research_migration', 'standard', $email_to, language_default(), $params, $from, TRUE)) {
                \Drupal::messenger()->addError('Error sending email message.');
              }
            } //research_migration_abstract_delete_project($form_state['values']['research_migration_project'])
            else {
              \Drupal::messenger()->addError(t('Error Dis-Approving and Deleting Entire Research Migration project.'));
            }
            // email 

          } //$form_state['values']['research_migration_actions'] == 3

        }
      } //user_access('research_migration project bulk manage code')
      return $msg;
    } //$form_state['clicked_button']['#value'] == 'Submit'
  }

}


/*************************************************************************** */
function _research_migration_details($research_migration_proposal_id): array {
  $proposal = \Drupal::database()->select('research_migration_proposal', 'p')
    ->fields('p')
    ->condition('id', $research_migration_proposal_id)
    ->execute()
    ->fetchObject();

  if (!$proposal) {
    return ['#markup' => t('Proposal not found.')];
  }

  // Abstract file (type A)
  $abstract_file = \Drupal::database()->select('research_migration_submitted_abstracts_file', 'f')
    ->fields('f')
    ->condition('proposal_id', $research_migration_proposal_id)
    ->condition('filetype', 'A')
    ->execute()
    ->fetchObject();

  $abstract_filename = (!empty($abstract_file->filename)) ? $abstract_file->filename : 'File not uploaded';

  // Case Directory file (type S)
  $case_dir_file = \Drupal::database()->select('research_migration_submitted_abstracts_file', 'f')
    ->fields('f')
    ->condition('proposal_id', $research_migration_proposal_id)
    ->condition('filetype', 'S')
    ->execute()
    ->fetchObject();

  $case_dir_filename = (!empty($case_dir_file->filename)) ? $case_dir_file->filename : 'File not uploaded';

  // Optional abstract submission check
  $abstracts_q = \Drupal::database()->select('research_migration_submitted_abstracts', 'a')
    ->fields('a')
    ->condition('proposal_id', $research_migration_proposal_id)
    ->execute()
    ->fetchObject();

  // Download project link
  $download_link = Link::fromTextAndUrl(
    'Download Research Migration project',
    Url::fromUserInput('/research-migration-project/full-download/project/' . $research_migration_proposal_id)
  )->toString();

  $markup = <<<HTML
<strong>Proposer Name:</strong><br />{$proposal->name_title} {$proposal->contributor_name}<br /><br />
<strong>Title of the Research Migration Project:</strong><br />{$proposal->project_title}<br /><br />
<strong>Uploaded an abstract (brief outline) of the project:</strong><br />{$abstract_filename}<br /><br />
<strong>Uploaded Case Directory Folder:</strong><br />{$case_dir_filename}<br /><br />
{$download_link}
HTML;

  return [
    '#type' => 'markup',
    '#markup' => Markup::create($markup),
  ];
}
function _bulk_list_of_research_migration_project() {
  $project_titles = [
    '0' => 'Please select...'
  ];

  $query = \Drupal::database()->select('research_migration_proposal', 'r');
  $query->fields('r');
  $query->condition('is_submitted', 1);
  $query->condition('approval_status', 1);
  $query->orderBy('project_title', 'ASC');

  $results = $query->execute();
  foreach ($results as $row) {
    $project_titles[$row->id] = $row->project_title . ' (Proposed by ' . $row->contributor_name . ')';
  }

  return $project_titles;
}
function _bulk_list_research_migration_actions(): array {
  return [
    0 => 'Please select...',
    1 => 'Approve Entire Research Migration Project',
    2 => 'Resubmit Project files',
    3 => 'Dis-Approve Entire Research Migration Project (This will delete Research Migration Project)',
    // 4 => 'Delete Entire Research Migration Project Including Proposal', // if needed
  ];
}
?>
