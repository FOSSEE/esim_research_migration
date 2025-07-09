<?php /**
 * @file
 * Contains \Drupal\esim_research_migration\Controller\DefaultController.
 */

namespace Drupal\esim_research_migration\Controller;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Service;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\Markup;

/**
 * Default controller for the esim_research_migration module.
 */
class DefaultController extends ControllerBase {

public function esim_research_migration_proposal_pending() {
  $pending_rows = [];

  $query = \Drupal::database()->select('research_migration_proposal', 'r');
  $query->fields('r');
  $query->condition('r.approval_status', 0);
  $query->orderBy('r.id', 'DESC');
  $pending_q = $query->execute();

  while ($pending_data = $pending_q->fetchObject()) {
    $submission_date = date('d-m-Y', $pending_data->creation_date);

    $user_link = Link::fromTextAndUrl(
      $pending_data->name_title . ' ' . $pending_data->contributor_name,
      Url::fromUri('internal:/user/' . $pending_data->uid)
    )->toRenderable();

    $approve_link = Link::fromTextAndUrl(
      'Approve',
      Url::fromUri('internal:/research-migration-project/manage-proposal/approve/' . $pending_data->id)
    )->toRenderable();

    $edit_link = Link::fromTextAndUrl(
      'Edit',
      Url::fromUri('internal:/research-migration-project/manage-proposal/edit/' . $pending_data->id)
    )->toRenderable();

    $action_links = [
      '#type' => 'inline_template',
      '#template' => '{{ approve }} | {{ edit }}',
      '#context' => [
        'approve' => render($approve_link),
        'edit' => render($edit_link),
      ],
    ];

    $pending_rows[] = [
      ['data' => $submission_date],
      ['data' => $user_link],
      ['data' => $pending_data->project_title],
      ['data' => $action_links],
    ];
  }

  if (empty($pending_rows)) {
    \Drupal::messenger()->addStatus(t('There are no pending proposals.'));
    return [
      '#markup' => '',
    ];
  }

  $pending_header = [
    t('Date of Submission'),
    t('Student Name'),
    t('Title of the Research Migration Project'),
    t('Action'),
  ];

  $output = [
    '#type' => 'table',
    '#header' => $pending_header,
    '#rows' => $pending_rows,
    '#attributes' => ['class' => ['research-migration-pending-table']],
    '#empty' => t('There are no pending proposals.'),
  ];

  return $output;
}

  public function esim_research_migration_proposal_all() {
    /* get pending proposals to be approved */
    $proposal_rows = [];
    $query = \Drupal::database()->select('research_migration_proposal');
    $query->fields('research_migration_proposal');
    $query->orderBy('id', 'DESC');
    $proposal_q = $query->execute();
    while ($proposal_data = $proposal_q->fetchObject()) {
      $approval_status = '';
      switch ($proposal_data->approval_status) {
        case 0:
          $approval_status = 'Pending';
          break;
        case 1:
          $approval_status = 'Approved';
          break;
        case 2:
          $approval_status = 'Dis-approved';
          break;
        case 3:
          $approval_status = 'Completed';
          break;
        case 5:
          $approval_status = 'On Hold';
          break;
        default:
          $approval_status = 'Unknown';
          break;
      } //$proposal_data->approval_status
      if ($proposal_data->actual_completion_date == 0) {
        $actual_completion_date = "Not Completed";
      } //$proposal_data->actual_completion_date == 0
      else {
        $actual_completion_date = date('d-m-Y', $proposal_data->actual_completion_date);
      }
      if ($proposal_data->approval_date == 0) {
        $approval_date = "Not Approved";
      } //$proposal_data->actual_completion_date == 0
      else {
        $approval_date = date('d-m-Y', $proposal_data->approval_date);
      }
 
$proposal_rows[] = [
  date('d-m-Y', $proposal_data->creation_date),

  // Link to user profile
  Link::fromTextAndUrl(
    $proposal_data->contributor_name,
    Url::fromRoute('entity.user.canonical', ['user' => $proposal_data->uid]))->toString(),

    $approval_date,
    $actual_completion_date,
    $approval_status,
    $proposal_data->project_title,

  // Action links: Status | Edit
  Link::fromTextAndUrl(
    'Status',
    Url::fromRoute('esim_research_migration.proposal_status_form', ['id' => $proposal_data->id])
  )->toString() .
  ' | ' .
  Link::fromTextAndUrl(
    'Edit',
    Url::fromRoute('esim_research_migration.proposal_edit_form', ['id' => $proposal_data->id])
  )->toString(),
];
    }
    if (empty($proposal_rows)) {
  \Drupal::messenger()->addStatus(t('There are no proposals.'));
  return ['#markup' => t('No proposals found.')];
}

$proposal_header = [
  'Date of Submission',
  'Student Name',
  'Title of the Research Migration project',
  'Date of Approval',
  'Date of Project Completion',
  'Status',
  'Action',
];

$output = [
  '#type' => 'table',
  '#header' => $proposal_header,
  '#rows' => $proposal_rows,
  '#attributes' => ['class' => ['proposal-table']],
  '#empty' => t('No proposals found.'),
];

return $output;
    
  }

public function esim_research_migration_proposal_edit_file_all() {
  $proposal_rows = [];
  $query = \Drupal::database()->select('research_migration_proposal', 'r');
  $query->fields('r');
  $query->condition('approval_status', [0, 1, 2], 'NOT IN');
  $query->orderBy('approval_status', 'DESC');
  $query->orderBy('id', 'DESC');
  $results = $query->execute();

  foreach ($results as $proposal_data) {
    // Determine status
    $approval_status = match ($proposal_data->approval_status) {
      0 => 'Pending',
      1 => 'Approved',
      2 => 'Dis-approved',
      3 => 'Completed',
      5 => 'On Hold',
      default => 'Unknown',
    };

    $actual_completion_date = $proposal_data->actual_completion_date == 0
      ? 'Not Completed'
      : date('d-m-Y', $proposal_data->actual_completion_date);

    $approval_date = $proposal_data->approval_date == 0
      ? 'Not Approved'
      : date('d-m-Y', $proposal_data->approval_date);

    $submission_date = date('d-m-Y', $proposal_data->creation_date);

    $user_link = Link::fromTextAndUrl(
      $proposal_data->contributor_name,
      Url::fromRoute('entity.user.canonical', ['user' => $proposal_data->uid])
    )->toString();

    $edit_link = Link::fromTextAndUrl(
      'Edit',
      Url::fromUserInput('/research-migration-project/abstract-code/edit-upload-files/' . $proposal_data->id)
    )->toString();

    $proposal_rows[] = [
      ['data' => $submission_date],
      ['data' => Markup::create($user_link)],
      ['data' => $proposal_data->project_title],
      ['data' => $approval_date],
      ['data' => $actual_completion_date],
      ['data' => $approval_status],
      ['data' => Markup::create($edit_link)],
    ];
  }

  if (empty($proposal_rows)) {
    \Drupal::messenger()->addStatus(t('There are no proposals.'));
    return [
      '#markup' => t('There are no proposals.'),
    ];
  }

  $proposal_header = [
    'Date of Submission',
    'Student Name',
    'Title of the Research Migration project',
    'Date of Approval',
    'Date of Project Completion',
    'Status',
    'Action',
  ];

  return [
    '#type' => 'table',
    '#header' => $proposal_header,
    '#rows' => $proposal_rows,
    '#empty' => t('No proposals found.'),
  ];
}


 public function esim_research_migration_abstract() {
  $user = \Drupal::currentUser();
  $return_html = "";
  $proposal_data = esim_research_migration_get_proposal();

  if (!$proposal_data) {
    return new RedirectResponse(Url::fromRoute('<front>')->toString());
  }

  // Fetch abstract submission
  $abstracts_q = \Drupal::database()->select('research_migration_submitted_abstracts')
    ->fields('research_migration_submitted_abstracts')
    ->condition('proposal_id', $proposal_data->id)
    ->execute()
    ->fetchObject();

  // Proposal details
  $abstracts_pro = \Drupal::database()->select('research_migration_proposal')
    ->fields('research_migration_proposal')
    ->condition('id', $proposal_data->id)
    ->execute()
    ->fetchObject();

  // Abstract PDF file (type A)
  $abstracts_pdf = \Drupal::database()->select('research_migration_submitted_abstracts_file')
    ->fields('research_migration_submitted_abstracts_file')
    ->condition('proposal_id', $proposal_data->id)
    ->condition('filetype', 'A')
    ->execute()
    ->fetchObject();

  $abstract_filename = 'File not uploaded';
  if ($abstracts_pdf && !empty($abstracts_pdf->filename) && $abstracts_pdf->filename !== 'NULL') {
    $abstract_filename = $abstracts_pdf->filename;
  }

  // Case directory (type S)
  $abstracts_query_process = \Drupal::database()->select('research_migration_submitted_abstracts_file')
    ->fields('research_migration_submitted_abstracts_file')
    ->condition('proposal_id', $proposal_data->id)
    ->condition('filetype', 'S')
    ->execute()
    ->fetchObject();

  $abstracts_query_process_filename = 'File not uploaded';
  $url = '';

  if ($abstracts_query_process && !empty($abstracts_query_process->filename) && $abstracts_query_process->filename !== 'NULL') {
    $abstracts_query_process_filename = $abstracts_query_process->filename;

    if (!empty($abstracts_q)) {
      if ($abstracts_q->is_submitted == 0) {
        $url = Link::fromTextAndUrl('Edit', Url::fromUserInput('/research-migration-project/abstract-code/upload'))->toString();
      }
    }
  } else {
    $url = Link::fromTextAndUrl('Upload Case Directory', Url::fromUserInput('/research-migration-project/abstract-code/upload'))->toString();
  }

  // Build HTML output
  $return_html .= '<strong>Contributor Name:</strong><br />' . $proposal_data->name_title . ' ' . $proposal_data->contributor_name . '<br /><br />';
  $return_html .= '<strong>Title of the Research Migration Project:</strong><br />' . $proposal_data->project_title . '<br /><br />';
  $return_html .= '<strong>Uploaded Synopsis Submission:</strong><br />' . $abstract_filename . '<br /><br />';
  $return_html .= '<strong>Uploaded Case Directory:</strong><br />' . $abstracts_query_process_filename . '<br /><br />';
  $return_html .= $url . '<br />';

  return [
    '#type' => 'markup',
    '#markup' => $return_html,
    '#allowed_tags' => ['strong', 'br', 'a', 'div'], // optional: improve sanitization
  ];
}


  public function esim_research_migration_download_full_project() {
    $user = \Drupal::currentUser();
    $id = arg(3);
    $root_path = esim_research_migration_path();
    //var_dump($root_path);die;
    $query = \Drupal::database()->select('research_migration_proposal');
    $query->fields('research_migration_proposal');
    $query->condition('id', $id);
    $research_migration_q = $query->execute();
    $research_migration_data = $research_migration_q->fetchObject();
    $research_migration_PATH = $research_migration_data->directory_name . '/';
    /* zip filename */
    $zip_filename = $root_path . 'zip-' . time() . '-' . rand(0, 999999) . '.zip';
    /* creating zip archive on the server */
    $zip = new ZipArchive();
    $zip->open($zip_filename, ZipArchive::CREATE);
    $query = \Drupal::database()->select('research_migration_proposal');
    $query->fields('research_migration_proposal');
    $query->condition('id', $id);
    $circuit_simulation_udc_q = $query->execute();
    $query = \Drupal::database()->select('research_migration_proposal');
    $query->fields('research_migration_proposal');
    $query->condition('id', $id);
    $query = \Drupal::database()->select('research_migration_submitted_abstracts_file');
    $query->fields('research_migration_submitted_abstracts_file');
    $query->condition('proposal_id', $id);
    $project_files = $query->execute();
    while ($esim_project_files = $project_files->fetchObject()) {
      $zip->addFile($root_path . $research_migration_PATH . $esim_project_files->filepath, $research_migration_PATH . str_replace(' ', '_', basename($esim_project_files->filename)));
    }
    $zip_file_count = $zip->numFiles;
    $zip->close();
    if ($zip_file_count > 0) {
      if ($user->uid) {
        /* download zip file */
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename="' . str_replace(' ', '_', $research_migration_data->project_title) . '.zip"');
        header('Content-Length: ' . filesize($zip_filename));
        ob_end_flush();
        ob_clean();
        flush();
        readfile($zip_filename);
        unlink($zip_filename);
      } //$user->uid
      else {
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename="' . str_replace(' ', '_', $research_migration_data->project_title) . '.zip"');
        header('Content-Length: ' . filesize($zip_filename));
        header("Content-Transfer-Encoding: binary");
        header('Expires: 0');
        header('Pragma: no-cache');
        ob_end_flush();
        ob_clean();
        flush();
        readfile($zip_filename);
        unlink($zip_filename);
      }
    } //$zip_file_count > 0
    else {
      \Drupal::messenger()->addError("There are no research migration project in this proposal to download");
      drupal_goto('research-migration-project/full-download/project');
    }
  }

  public function esim_research_migration_completed_proposals_all() {
    $output = "";
    $query = \Drupal::database()->select('research_migration_proposal');
    $query->fields('research_migration_proposal');
    $query->condition('approval_status', 3);
    $query->orderBy('actual_completion_date', 'DESC');
    //$query->condition('is_completed', 1);
    $result = $query->execute();

    //var_dump($research_migration_abstract);die;
    if ($result->rowCount() == 0) {
      $output .= "Currently, there are no submissions in this section. Click <a href='proposal'>here</a> to propose a Research Migration Project." . "<hr>";

    } //$result->rowCount() == 0
    else {
      $output .= "Work has been completed for the following research migrations. We welcome your contributions." . "<hr>";
      $preference_rows = [];
      $i = $result->rowCount();
      while ($row = $result->fetchObject()) {
        $proposal_id = $row->id;
        $query1 = \Drupal::database()->select('research_migration_submitted_abstracts_file');
        $query1->fields('research_migration_submitted_abstracts_file');
        $query1->condition('file_approval_status', 1);
        $query1->condition('proposal_id', $proposal_id);
        $research_migration_files = $query1->execute();
        $research_migration_abstract = $research_migration_files->fetchObject();

        // @FIXME
        // l() expects a Url object, created from a route name or external URI.
        // $project_title = l($row->project_title, "research-migration-project/research-migration-run/" . $row->id);

        $year = date("Y", $row->actual_completion_date);
        $preference_rows[] = [
          $i,
          $project_title,
          $row->contributor_name,
          $row->institute,
          $year,
        ];
        $i--;
      } //$row = $result->fetchObject()
      $preference_header = [
        'No',
        'Research Migration Project',
        'Contributor Name',
        'University/ Institute',
        'Year of Completion',
      ];
      // @FIXME
      // theme() has been renamed to _theme() and should NEVER be called directly.
      // Calling _theme() directly can alter the expected output and potentially
      // introduce security issues (see https://www.drupal.org/node/2195739). You
      // should use renderable arrays instead.
      // 
      // 
      // @see https://www.drupal.org/node/2195739
      // $output .= theme('table', array(
      // 			'header' => $preference_header,
      // 			'rows' => $preference_rows
      // 		));

    }
    return $output;
  }

  public function esim_research_migration_progress_all() {
    $page_content = "";
    $query = \Drupal::database()->select('research_migration_proposal');
    $query->fields('research_migration_proposal');
    $query->condition('approval_status', 1);
    $query->condition('is_completed', 0);
    $query->orderBy('approval_date', 'DESC');
    $result = $query->execute();
    if ($result->rowCount() == 0) {
      $page_content .= "Currently, there are no submissions in progress. Click <a href='proposal'>here</a> to propose a Research Migration Project.<hr>";
    } //$result->rowCount() == 0
    else {
      $page_content .= "Work is in progress for the following submissions under the Research Migration Project<hr>";
      $preference_rows = [];
      $i = $result->rowCount();
      while ($row = $result->fetchObject()) {
        $approval_date = date("Y", $row->approval_date);
        $preference_rows[] = [
          $i,
          $row->project_title,
          $row->contributor_name,
          $row->institute,
          $approval_date,
        ];
        $i--;
      } //$row = $result->fetchObject()
      $preference_header = [
        'No',
        'Research Migration Project',
        'Contributor Name',
        'Institute',
        'Year',
      ];
      // @FIXME
      // theme() has been renamed to _theme() and should NEVER be called directly.
      // Calling _theme() directly can alter the expected output and potentially
      // introduce security issues (see https://www.drupal.org/node/2195739). You
      // should use renderable arrays instead.
      // 
      // 
      // @see https://www.drupal.org/node/2195739
      // $page_content .= theme('table', array(
      // 			'header' => $preference_header,
      // 			'rows' => $preference_rows
      // 		));

    }
    return $page_content;
  }

  public function list_of_available_project_titles() {
    $output = "";
    //$static_url = "https://static.fossee.in/esim/project-titles/";
    $preference_rows = [];
    $i = 1;
    $query = \Drupal::database()->query("SELECT * from rm_list_of_project_titles WHERE {rm_project_title_name} NOT IN( SELECT  project_title from research_migration_proposal WHERE approval_status = 0 OR approval_status = 1 OR approval_status = 3)");
    while ($result = $query->fetchObject()) {
      // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $preference_rows[] = array(
// 				$i,
// 				//print_r(array_keys($case_studies_list))
// 				$result->rm_project_title_name,
// 				l('Click Here', $result->rm_project_link , array('attributes' => array('target'=>'_blank')))
// 				//l(Download, 'research-migration-project/download/project-title-file/' .$result->id)
// 				);

      $i++;
    }
    $preference_header = [
      'No',
      'List of available projects',
      'Link to the paper',
    ];
    // @FIXME
    // theme() has been renamed to _theme() and should NEVER be called directly.
    // Calling _theme() directly can alter the expected output and potentially
    // introduce security issues (see https://www.drupal.org/node/2195739). You
    // should use renderable arrays instead.
    // 
    // 
    // @see https://www.drupal.org/node/2195739
    // $output .= theme('table', array(
    // 			'header' => $preference_header,
    // 			'rows' => $preference_rows
    // 		));


    return $output;
  }

  public function download_research_migration_project_title_files() {
    $id = arg(3);
    $root_path = esim_research_migration_project_titles_resource_file_path();
    $query = \Drupal::database()->select('rm_list_of_project_titles');
    $query->fields('rm_list_of_project_titles');
    $query->condition('id', $id);
    $result = $query->execute();
    $rm_project_files_list = $result->fetchObject();
    //$directory_name = $case_study_project_files_list->filepath;
    $abstract_file = $rm_project_files_list->filepath;
    ob_clean();
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: public");
    header("Content-Description: File Transfer");
    header("Content-Type: application/pdf");
    header('Content-disposition: attachment; filename="' . $abstract_file . '"');
    header("Content-Length: " . filesize($root_path . $abstract_file));
    header("Content-Transfer-Encoding: binary");
    header("Expires: 0");
    header("Pragma: no-cache");
    readfile($root_path . $abstract_file);
    ob_end_flush();
    ob_clean();
  }

  public function esim_research_migration_project_files() {
    $proposal_id = arg(3);
    $root_path = esim_research_migration_path();
    // $query = db_select('research_migration_submitted_abstracts_file');
    // $query->fields('research_migration_submitted_abstracts_file');
    // $query->condition('proposal_id', $proposal_id);
    // $query->condition('filetype', 'A');
    // $result = $query->execute();
    // $esim_research_migration_project_files = $result->fetchObject();
    $query1 = \Drupal::database()->select('research_migration_proposal');
    $query1->fields('research_migration_proposal');
    $query1->condition('id', $proposal_id);
    $result1 = $query1->execute();
    $research_migration = $result1->fetchObject();
    $directory_name = $research_migration->directory_name . '/';
    $str = substr($research_migration->samplefilepath, strrpos($research_migration->samplefilepath, '/'));
    $resource_file = ltrim($str, '/');
    $abstract_file = $research_migration->samplefilepath;
    //var_dump('abstract_file(filepath):' . $abstract_file . '    resource_file(filename):' . $resource_file);die;
    ob_clean();
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: public");
    header("Content-Description: File Transfer");
    header("Content-Type: application/pdf");
    header('Content-disposition: attachment; filename="' . $resource_file . '"');
    header("Content-Length: " . filesize($root_path . $abstract_file));
    header("Content-Transfer-Encoding: binary");
    header("Expires: 0");
    header("Pragma: no-cache");
    readfile($root_path . $abstract_file);
    ob_end_flush();
    ob_clean();
  }

  public function _list_research_migration_certificates() {
    $user = \Drupal::currentUser();
    $query_id = \Drupal::database()->query("SELECT id FROM research_migration_proposal WHERE approval_status=3 AND uid= :uid", [
      ':uid' => $user->uid
      ]);
    $exist_id = $query_id->fetchObject();
    //var_dump($exist_id->id);die;
    if ($exist_id) {
      if ($exist_id->id) {
        if ($exist_id->id < 1) {
          \Drupal::messenger()->addStatus('<strong>You need to propose a <a href="https://esim.fossee.in/research-migration-project/proposal">Research Migration Proposal</a></strong> or if you have already proposed then your Research Migration is under reviewing process');
          return '';
        } //$exist_id->id < 3
        else {
          $search_rows = [];
          global $output;
          $output = '';
          $query3 = \Drupal::database()->query("SELECT id,project_title,contributor_name FROM research_migration_proposal WHERE approval_status=3 AND uid= :uid", [
            ':uid' => $user->uid
            ]);
          while ($search_data3 = $query3->fetchObject()) {
            if ($search_data3->id) {
              // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $search_rows[] = array(
// 						$search_data3->project_title,
// 						$search_data3->contributor_name,
// 						l('Download Certificate', 'research-migration-project/certificates/generate-pdf/' . $search_data3->id)
// 					);

            } //$search_data3->id
          } //$search_data3 = $query3->fetchObject()
          if ($search_rows) {
            $search_header = [
              'Project Title',
              'Contributor Name',
              'Download Certificates',
            ];
            // @FIXME
            // theme() has been renamed to _theme() and should NEVER be called directly.
            // Calling _theme() directly can alter the expected output and potentially
            // introduce security issues (see https://www.drupal.org/node/2195739). You
            // should use renderable arrays instead.
            // 
            // 
            // @see https://www.drupal.org/node/2195739
            // $output        = theme('table', array(
            // 					'header' => $search_header,
            // 					'rows' => $search_rows
            // 				));

            return $output;
          } //$search_rows
          else {
            echo ("Error");
            return '';
          }
        }
      }
    } //$exist_id->id
    else {
      \Drupal::messenger()->addStatus('<strong>You need to propose a <a href="https://esim.fossee.in/research-migration-project/proposal">Research Migration Proposal</a></strong> or if you have already proposed then your Research Migration is under reviewing process');
      $page_content = "<span style='color:red;'> No certificate available </span>";
      return $page_content;
    }
  }

  public function verify_certificates($qr_code = 0) {
    $qr_code = arg(3);
    $page_content = "";
    if ($qr_code) {
      $page_content = verify_qrcode_fromdb($qr_code);
    } //$qr_code
    else {
      $verify_certificates_form = drupal_get_form("verify_certificates_form");
      $page_content = drupal_render($verify_certificates_form);
    }
    return $page_content;
  }

}
