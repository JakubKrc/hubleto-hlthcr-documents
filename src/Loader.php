<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments;

use Hubleto\App\Community\Customers\Models\Customer;
use Hubleto\App\Community\Workflow\Models\Workflow as WorkflowModel;
use Hubleto\App\Community\Workflow\Models\WorkflowStep;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\Branch;
use Hubleto\App\External\WaiBlue\HlthcrCore\Services\DemoData;

class Loader extends \Hubleto\App\External\WaiBlue\HlthcrCore\HlthcrApp
{
  // Tags of the document workflow steps: a neutral list, because the two legacy apps have different states.
  const STEP_WAITING_FOR_INFORMATION = 'hlthcr-document-waiting-for-information';
  const STEP_WAITING_FOR_PROCESSING = 'hlthcr-document-waiting-for-processing';
  const STEP_IN_PROGRESS = 'hlthcr-document-in-progress';
  const STEP_RETURNED_WITH_COMMENTS = 'hlthcr-document-returned-with-comments';
  const STEP_DONE = 'hlthcr-document-done';
  const STEP_SENT_TO_CLIENT = 'hlthcr-document-sent-to-client';
  const STEP_INVOICED = 'hlthcr-document-invoiced';

  // A document in these steps is done (the calendar's "done" filter).
  const DONE_STEP_TAGS = [self::STEP_DONE, self::STEP_SENT_TO_CLIENT, self::STEP_INVOICED];

  public function init(): void
  {
    parent::init();

    $this->router()->get([
      '/^hlthcr-documents\/?$/' => Controllers\Home::class,
      '/^hlthcr-documents\/settings\/?$/' => Controllers\Settings::class,
      '/^hlthcr-documents\/documents\/(?<idDocument>\d+)\/download\/?$/' => Controllers\DownloadDocument::class,
    ]);
    $this->router()->crud('hlthcr-documents/documents', Controllers\Documents::class);

    $this->addSearchSwitch('d', 'documents');

    /** @var \Hubleto\App\Community\Calendar\Manager $calendarManager */
    $calendarManager = $this->getService(\Hubleto\App\Community\Calendar\Manager::class);
    $calendarManager->addCalendar($this, 'hlthcr-documents', Calendar::class);

    // One listener object for both events: it keeps the files between them.
    $auditChanges = $this->getService(EventListeners\AuditChanges::class);
    $this->eventManager()->addEventListener('onModelBeforeDelete', $auditChanges);
    $this->eventManager()->addEventListener('onModelAfterDelete', $auditChanges);

    /** @var \Hubleto\App\Community\Workflow\Manager $workflowManager */
    $workflowManager = $this->getService(\Hubleto\App\Community\Workflow\Manager::class);
    $workflowManager->addWorkflowGroup($this, Models\Document::WORKFLOW_GROUP, Workflow::class);
  }

  public function installApp(int $round): void
  {
    if ($round == 1) {
      $this->getModel(Models\Document::class)->upgradeSchema();
      // "php hubleto app install" runs only round 1, so foreign keys are created here too (Hubleto skips them later).
      $this->installForeignKeys();
      $this->installDocumentWorkflow();
    }
  }

  /**
   * Creates the document workflow, or adds the steps an older install does not have yet.
   * The first step is the default for new documents.
   */
  private function installDocumentWorkflow(): void
  {
    /** @var WorkflowModel */
    $mWorkflow = $this->getModel(WorkflowModel::class);
    /** @var WorkflowStep */
    $mWorkflowStep = $this->getModel(WorkflowStep::class);

    $workflow = $mWorkflow->record->where('group', Models\Document::WORKFLOW_GROUP)->first();
    if ($workflow) {
      $idWorkflow = (int) $workflow->id;
    } else {
      $idWorkflow = (int) $mWorkflow->record->recordCreate([
        'name' => $this->translate('Work documents'),
        'show_in_kanban' => 1,
        'order' => 60,
        'group' => Models\Document::WORKFLOW_GROUP,
      ])['id'];
    }

    $steps = [
      ['name' => $this->translate('Waiting for information'), 'color' => '#9e9e9e', 'tag' => self::STEP_WAITING_FOR_INFORMATION],
      ['name' => $this->translate('Waiting for processing'), 'color' => '#b0bec5', 'tag' => self::STEP_WAITING_FOR_PROCESSING],
      ['name' => $this->translate('In progress'), 'color' => '#e27300', 'tag' => self::STEP_IN_PROGRESS],
      ['name' => $this->translate('Returned with comments'), 'color' => '#c62828', 'tag' => self::STEP_RETURNED_WITH_COMMENTS],
      ['name' => $this->translate('Done'), 'color' => '#4caf50', 'tag' => self::STEP_DONE],
      ['name' => $this->translate('Sent to the client'), 'color' => '#0062b1', 'tag' => self::STEP_SENT_TO_CLIENT],
      ['name' => $this->translate('Invoiced'), 'color' => '#5f5f5f', 'tag' => self::STEP_INVOICED],
    ];
    $order = 1;
    foreach ($steps as $step) {
      $existingStep = $mWorkflowStep->record->where('id_workflow', $idWorkflow)->where('tag', $step['tag'])->first();
      if ($existingStep) {
        $existingStep->update(['order' => $order]);
      } else {
        $mWorkflowStep->record->recordCreate([
          'id_workflow' => $idWorkflow,
          'name' => $step['name'],
          'color' => $step['color'],
          'tag' => $step['tag'],
          'order' => $order,
        ]);
      }
      $order++;
    }
  }

  public function search(array $expressions): array
  {
    /** @var Models\Document */
    $mDocument = $this->getModel(Models\Document::class);
    $documentsQuery = $mDocument->record->prepareReadQuery()->with(['CUSTOMER']);
    foreach ($expressions as $expression) {
      $documentsQuery = $documentsQuery->where('hlthcr_documents.name', 'like', '%' . $expression . '%');
    }

    $results = [];
    foreach ($documentsQuery->limit(20)->get()->toArray() as $document) {
      $results[] = [
        'id' => $document['id'],
        'label' => $document['name'],
        'url' => 'hlthcr-documents/documents/' . $document['id'],
        'description' => $document['CUSTOMER']['name'] ?? '',
      ];
    }
    return $results;
  }

  public function renderSecondSidebar(): string
  {
    return '
      ' . $this->secondSidebarTitle() . '
      <div class="app-sidebar-buttons">
        ' . $this->secondSidebarButton('hlthcr-documents/documents', 'fas fa-file-lines', $this->translate('Work documents')) . '
        ' . $this->secondSidebarButton('hlthcr-documents/settings', 'fas fa-cog', $this->translate('Settings')) . '
      </div>
    ';
  }

  public function renderAlerts(): string
  {
    return '';
  }

  // No number in the menu: neither legacy app shows one.
  public function getSidebarBadgeNumber(): int
  {
    return 0;
  }

  /**
   * Demo documents of the demo clients (FAKE data, no real files).
   * Records are inserted directly: the model rules use the logged-in user, and there is none on the command line.
   */
  public function generateHlthcrDemoData(): void
  {
    /** @var Models\Document */
    $mDocument = $this->getModel(Models\Document::class);

    $demoCompanyIds = $this->getService(DemoData::class)->getDemoCompanyIds();
    $demoCustomers = $this->getModel(Customer::class)->record->whereIn('company_id', $demoCompanyIds)->get();

    foreach ($demoCustomers as $demoCustomer) {
      $customerHasDocuments = $mDocument->record->where('id_customer', $demoCustomer->id)->exists();
      if ($customerHasDocuments) continue;

      $firstBranch = $this->getModel(Branch::class)->record->where('id_customer', $demoCustomer->id)->orderBy('id')->first();

      $this->createDemoDocument([
        'name' => 'Posudok o riziku',
        'id_customer' => $demoCustomer->id,
        'priority' => Models\Document::PRIORITY_HIGH,
        'id_author' => $demoCustomer->id_owner,
        'date_created' => date('Y-m-d', strtotime('-20 days')),
      ], self::STEP_IN_PROGRESS);

      $this->createDemoDocument([
        'name' => 'Kategorizácia prác',
        'id_customer' => $demoCustomer->id,
        'priority' => Models\Document::PRIORITY_MEDIUM,
        'id_author' => $demoCustomer->id_owner,
        'date_created' => date('Y-m-d', strtotime('-5 days')),
      ], self::STEP_WAITING_FOR_INFORMATION);

      $this->createDemoDocument([
        'name' => 'Hodnotenie zdravotných rizík',
        'id_customer' => $demoCustomer->id,
        'priority' => Models\Document::PRIORITY_MEDIUM,
        'id_author' => $demoCustomer->id_owner,
        'date_created' => date('Y-m-d', strtotime('-2 days')),
      ], self::STEP_WAITING_FOR_PROCESSING);

      $this->createDemoDocument([
        'name' => 'Program ochrany zdravia pri práci',
        'id_customer' => $demoCustomer->id,
        'priority' => Models\Document::PRIORITY_HIGH,
        'id_author' => $demoCustomer->id_owner,
        'date_created' => date('Y-m-d', strtotime('-30 days')),
      ], self::STEP_RETURNED_WITH_COMMENTS);

      if ($firstBranch) {
        $this->createDemoDocument([
          'name' => 'Prevádzkový poriadok prevádzky',
          'id_customer' => $demoCustomer->id,
          'id_branch' => $firstBranch->id,
          'priority' => Models\Document::PRIORITY_LOW,
          'id_author' => $demoCustomer->id_owner,
          'date_created' => date('Y-m-d', strtotime('-60 days')),
        ], self::STEP_DONE);
      }
    }
  }

  private function createDemoDocument(array $document, string $stepTag): void
  {
    $workflowStep = $this->getModel(WorkflowStep::class)->record->where('tag', $stepTag)->first();

    $document['id_workflow'] = $workflowStep ? $workflowStep->id_workflow : null;
    $document['id_workflow_step'] = $workflowStep ? $workflowStep->id : null;

    $this->getModel(Models\Document::class)->record->create($document);
  }
}
