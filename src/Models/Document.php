<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments\Models;

use Hubleto\App\External\WaiBlue\HlthcrCore\Services\ProtectedFile;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Traits\EmptyDatesAsNull;
use Hubleto\App\Community\Auth\Models\User;
use Hubleto\App\Community\Customers\Models\Customer;
use Hubleto\App\Community\Workflow\Models\Workflow;
use Hubleto\App\Community\Workflow\Models\WorkflowStep;
use Hubleto\App\External\WaiBlue\HlthcrAudits\Models\Audit;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\Branch;
use Hubleto\App\External\WaiBlue\HlthcrCore\Controllers\Api\CustomerLookup;
use Hubleto\Framework\Db\Column\Date;
use Hubleto\App\External\WaiBlue\HlthcrCore\Db\Column\StoredFile;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Traits\KeepsStoredFiles;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Traits\StaysInOwnWorkflow;
use Hubleto\Framework\Db\Column\Integer;
use Hubleto\Framework\Db\Column\Lookup;
use Hubleto\Framework\Db\Column\Varchar;
use Hubleto\Framework\Exceptions\RecordSaveException;

/**
 * Work document of a client, of one of its branches or of an audit (legacy "Dokumenty").
 * Its state is a Workflow step, so Hubleto keeps the state history and shows a kanban board.
 */
class Document extends \Hubleto\App\External\WaiBlue\HlthcrCore\Models\HlthcrModel
{
  use KeepsStoredFiles;
  use EmptyDatesAsNull;
  use StaysInOwnWorkflow;

  const WORKFLOW_GROUP = 'hlthcr_documents';

  // Priority as in both legacy apps: 1 is the most urgent (red), 3 the least (green).
  const PRIORITY_HIGH = 1;
  const PRIORITY_MEDIUM = 2;
  const PRIORITY_LOW = 3;

  public string $table = 'hlthcr_documents';
  public string $recordManagerClass = RecordManagers\Document::class;
  public ?string $lookupSqlValue = '{%TABLE%}.name';
  public ?string $lookupUrlDetail = 'hlthcr-documents/documents/{%ID%}';

  public array $relations = [
    'CUSTOMER' => [ self::BELONGS_TO, Customer::class, 'id_customer', 'id' ],
    'BRANCH' => [ self::BELONGS_TO, Branch::class, 'id_branch', 'id' ],
    'AUDIT' => [ self::BELONGS_TO, Audit::class, 'id_audit', 'id' ],
    'AUTHOR' => [ self::BELONGS_TO, User::class, 'id_author', 'id' ],
    'WORKFLOW' => [ self::BELONGS_TO, Workflow::class, 'id_workflow', 'id' ],
    'WORKFLOW_STEP' => [ self::BELONGS_TO, WorkflowStep::class, 'id_workflow_step', 'id' ],
  ];

  /** @var string File of the document being deleted; removed after the record is gone. */
  private string $fileOfDeletedDocument = '';

  public function describeColumns(): array
  {
    return array_merge(parent::describeColumns(), [
      'name' => (new Varchar($this, $this->translate('Document name')))->setRequired()->setDefaultVisible(),
      'id_customer' => (new Lookup($this, $this->translate('Client'), Customer::class, 'RESTRICT'))
        ->setRequired()
        ->setDefaultVisible()
        // Only clients visible to the user (the community lookup is not scoped).
        ->setEndpoint(CustomerLookup::ENDPOINT)
      ,
      'id_branch' => (new Lookup($this, $this->translate('Branch'), Branch::class, 'RESTRICT'))
        ->setDefaultVisible()
        ->setHint($this->translate('Leave empty for a document of the whole client.'))
      ,
      'id_audit' => (new Lookup($this, $this->translate('Audit'), Audit::class, 'CASCADE'))
        ->setDefaultVisible()
        ->setHint($this->translate('Only for a document of an audit.'))
      ,
      'date_created' => (new Date($this, $this->translate('Created on')))->setRequired()->setDefaultVisible(),
      'file' => (new StoredFile($this, $this->translate('Document')))
        ->setDownloadUrl('hlthcr-documents/documents/{%ID%}/download')
        ->setFolderPath('hlthcr/documents')
        ->setRenamePattern('{%FILENAME_ASCII%}__{%Y%}{%M%}{%D%}_{%H%}{%I%}{%S%}.{%EXT%}')
      ,
      'priority' => (new Integer($this, $this->translate('Priority')))
        ->setRequired()
        ->setDefaultVisible()
        ->setDefaultValue(self::PRIORITY_LOW)
        ->setEnumValues([
          self::PRIORITY_HIGH => '1',
          self::PRIORITY_MEDIUM => '2',
          self::PRIORITY_LOW => '3',
        ])
        ->setEnumCssClasses([
          self::PRIORITY_HIGH => 'bg-red-100 text-red-800',
          self::PRIORITY_MEDIUM => 'bg-yellow-100 text-yellow-800',
          self::PRIORITY_LOW => 'bg-green-100 text-green-800',
        ])
      ,
      'id_author' => (new Lookup($this, $this->translate('Created by'), User::class))
        ->setDefaultVisible()
        ->setReadonly()
      ,
      'id_workflow' => (new Lookup($this, $this->translate('Workflow'), Workflow::class))->setReadonly(),
      'id_workflow_step' => (new Lookup($this, $this->translate('State'), WorkflowStep::class))
        ->setDefaultVisible()
        ->setReadonly()
      ,
    ]);
  }

  public function describeTable(): \Hubleto\Framework\Description\Table
  {
    $description = parent::describeTable();
    $description->ui['addButtonText'] = $this->translate('Add document');
    $description->ui['orderBy'] = ['field' => 'date_created', 'direction' => 'desc'];
    $description->show(['header', 'fulltextSearch', 'columnSearch', 'moreActionsButton']);
    $description->hide(['footer']);

    $description->addFilter(
      RecordManagers\Document::STATE_FILTER,
      Workflow::buildTableFilterForWorkflowSteps($this, $this->translate('State'))
    );
    $description->addFilter(RecordManagers\Document::PRIORITY_FILTER, [
      'title' => $this->translate('Priority'),
      'options' => [
        RecordManagers\Document::PRIORITY_FILTER_ALL => $this->translate('All'),
        self::PRIORITY_HIGH => '1',
        self::PRIORITY_MEDIUM => '2',
        self::PRIORITY_LOW => '3',
      ],
    ]);

    return $description;
  }

  public function describeForm(): \Hubleto\Framework\Description\Form
  {
    $description = parent::describeForm();
    $description->defaultValues['date_created'] = date('Y-m-d');
    $description->defaultValues['priority'] = self::PRIORITY_LOW;
    $description->defaultValues['id_author'] = $this->authProvider()->getUserId();
    return $description;
  }

  public function getRelationsIncludedInLoadTableData(): array|null
  {
    return ['CUSTOMER', 'BRANCH', 'AUTHOR', 'WORKFLOW_STEP'];
  }

  public function getMaxReadLevelForLoadTableData(): int
  {
    return 1;
  }

  public function onBeforeCreate(array $record): array
  {
    $record = parent::onBeforeCreate($record);
    $record = $this->emptyDatesAsNull($record);
    // None when the document is created on the command line (demo data).
    $idUser = (int) $this->authProvider()->getUserId();
    $record['id_author'] = $idUser > 0 ? $idUser : null;
    $record = $this->keepStoredFile($record, 'file', null);

    return $this->setOwnersFromAuditAndBranch($record);
  }

  public function onBeforeUpdate(array $record): array
  {
    $record = parent::onBeforeUpdate($record);
    $record = $this->emptyDatesAsNull($record);
    $this->refuseWorkflowOfOtherGroup($record, self::WORKFLOW_GROUP);

    // The author stays as when the document was created.
    $originalDocument = $this->record->where('id', (int) $record['id'])->first();
    $record = $this->keepStoredFile($record, 'file', $originalDocument);
    if ($originalDocument) {
      $record['id_author'] = $originalDocument->id_author;
    }

    return $this->setOwnersFromAuditAndBranch($record);
  }

  public function onAfterCreate(array $savedRecord): array
  {
    $savedRecord = parent::onAfterCreate($savedRecord);

    // New documents start in the first step of the document workflow.
    /** @var Workflow */
    $mWorkflow = $this->getModel(Workflow::class);
    $savedRecord = $mWorkflow->applyDefaultWorkflow($savedRecord, self::WORKFLOW_GROUP);

    // Only the workflow columns: saving the whole record again would store the uploaded file twice.
    $this->record->where('id', $savedRecord['id'])->update([
      'id_workflow' => $savedRecord['id_workflow'] ?? null,
      'id_workflow_step' => $savedRecord['id_workflow_step'] ?? null,
    ]);
    $this->logFirstWorkflowStep($savedRecord);

    // Returned as saved in the database (e.g. the stored file path instead of the uploaded data).
    return $this->record->where('id', $savedRecord['id'])->first()->toArray();
  }

  public function onBeforeDelete(int $id): int
  {
    $id = parent::onBeforeDelete($id);
    $document = $this->record->where('id', $id)->first();
    $this->fileOfDeletedDocument = $document ? (string) $document->file : '';
    return $id;
  }

  // Deleting a document also deletes its file (both legacy apps).
  public function onAfterDelete(int $id): int
  {
    $id = parent::onAfterDelete($id);
    if ($this->fileOfDeletedDocument == '') return $id;

    $this->getService(ProtectedFile::class)->delete($this->fileOfDeletedDocument);
    $this->fileOfDeletedDocument = '';

    return $id;
  }

  // An audit document belongs to the audit's branch, a branch document to the branch's client.
  // An empty branch or client is filled in; one that does not match is refused, not replaced.
  private function setOwnersFromAuditAndBranch(array $record): array
  {
    $idAudit = (int) ($record['id_audit'] ?? 0);
    $audit = $this->getModel(Audit::class)->record->where('id', $idAudit)->first();
    if ($audit) {
      $idSubmittedBranch = (int) ($record['id_branch'] ?? 0);
      if ($idSubmittedBranch > 0 && $idSubmittedBranch != (int) $audit->id_branch) {
        throw new RecordSaveException($this->translate('The audit is not one of the chosen branch.'));
      }
      $record['id_branch'] = $audit->id_branch;
    }

    $idBranch = (int) ($record['id_branch'] ?? 0);
    $branch = $this->getModel(Branch::class)->record->where('id', $idBranch)->first();
    if ($branch) {
      $idSubmittedCustomer = (int) ($record['id_customer'] ?? 0);
      if ($idSubmittedCustomer > 0 && $idSubmittedCustomer != (int) $branch->id_customer) {
        throw new RecordSaveException($this->translate('The branch is not one of the chosen client.'));
      }
      $record['id_customer'] = $branch->id_customer;
    }

    return $record;
  }
}
