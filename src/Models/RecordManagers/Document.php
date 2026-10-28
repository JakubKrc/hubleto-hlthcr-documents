<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments\Models\RecordManagers;

use Hubleto\App\External\WaiBlue\HlthcrCore\Models\RecordManagers\Traits\DefaultOrder;
use Hubleto\App\Community\Auth\Models\RecordManagers\User;
use Hubleto\App\Community\Customers\Models\RecordManagers\Customer;
use Hubleto\App\Community\Workflow\Models\RecordManagers\Workflow;
use Hubleto\App\Community\Workflow\Models\RecordManagers\WorkflowStep;
use Hubleto\App\External\WaiBlue\HlthcrAudits\Models\RecordManagers\Audit;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\RecordManagers\Branch;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Services\BranchScope;
use Hubleto\App\External\WaiBlue\HlthcrCore\Services\AccessScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends \Hubleto\Erp\RecordManager
{
  use DefaultOrder;

  public $table = 'hlthcr_documents';

  const STATE_FILTER = 'fDocumentState';
  const PRIORITY_FILTER = 'fDocumentPriority';
  const PRIORITY_FILTER_ALL = 0;

  public function CUSTOMER(): BelongsTo
  {
    return $this->belongsTo(Customer::class, 'id_customer', 'id');
  }

  public function BRANCH(): BelongsTo
  {
    return $this->belongsTo(Branch::class, 'id_branch', 'id');
  }

  public function AUDIT(): BelongsTo
  {
    return $this->belongsTo(Audit::class, 'id_audit', 'id');
  }

  public function AUTHOR(): BelongsTo
  {
    return $this->belongsTo(User::class, 'id_author', 'id');
  }

  public function WORKFLOW(): BelongsTo
  {
    return $this->belongsTo(Workflow::class, 'id_workflow', 'id');
  }

  public function WORKFLOW_STEP(): BelongsTo
  {
    return $this->belongsTo(WorkflowStep::class, 'id_workflow_step', 'id');
  }

  public function prepareReadQuery(mixed $query = null, int $level = 0, array|null $includeRelations = null): mixed
  {
    $query = parent::prepareReadQuery($query, $level, $includeRelations);
    return $this->restrictToVisibleDocuments($query);
  }

  public function prepareLookupQuery(string $search): mixed
  {
    $query = parent::prepareLookupQuery($search);
    return $this->restrictToVisibleDocuments($query);
  }

  /**
   * A branch document follows the branch's hygienist, a document of the whole client the client's hygienist.
   * Medical staff see no working documents.
   */
  private function restrictToVisibleDocuments(mixed $query): mixed
  {
    $hubleto = \Hubleto\Erp\Loader::getGlobalApp();
    /** @var AccessScope */
    $accessScope = $hubleto->getService(AccessScope::class);
    /** @var BranchScope */
    $branchScope = $hubleto->getService(BranchScope::class);
    $table = $this->table;

    $query = $accessScope->restrictToWorkRecordReaders($query);

    return $query->where(function ($visibleDocuments) use ($table, $accessScope, $branchScope) {
      $visibleDocuments->where(function ($branchDocuments) use ($table, $branchScope) {
        $branchDocuments->where($table . '.id_branch', '>', 0);
        $branchScope->restrictToVisibleBranches($branchDocuments, $table . '.id_branch');
      });
      $visibleDocuments->orWhere(function ($clientDocuments) use ($table, $accessScope) {
        $clientDocuments->whereRaw('coalesce(' . $table . '.id_branch, 0) = 0');
        $accessScope->restrictToVisibleCustomers($clientDocuments, $table . '.id_customer');
      });
    });
  }

  public function addUrlFiltersToQuery(mixed $query): mixed
  {
    $query = parent::addUrlFiltersToQuery($query);

    $hubleto = \Hubleto\Erp\Loader::getGlobalApp();
    $router = $hubleto->router();

    // Customer form tab: all documents of the client, including its branches' documents.
    $idCustomer = $router->urlParamAsInteger('idCustomer');
    if ($idCustomer > 0) {
      $query = $query->where($this->table . '.id_customer', $idCustomer);
    }

    $idBranch = $router->urlParamAsInteger('idBranch');
    if ($idBranch > 0) {
      $query = $query->where($this->table . '.id_branch', $idBranch);
    }

    // Audit form tab "Dokumenty".
    $idAudit = $router->urlParamAsInteger('idAudit');
    if ($idAudit > 0) {
      $query = $query->where($this->table . '.id_audit', $idAudit);
    }

    $filters = $router->urlParamAsArray('filters');

    $priority = (int) ($filters[self::PRIORITY_FILTER] ?? self::PRIORITY_FILTER_ALL);
    if ($priority != self::PRIORITY_FILTER_ALL) {
      $query = $query->where($this->table . '.priority', $priority);
    }

    $selectedStates = (array) ($filters[self::STATE_FILTER] ?? []);

    return Workflow::applyWorkflowStepFilter($this->model, $query, $selectedStates);
  }

  public function prepareLookupData(array $dataRaw): array
  {
    return parent::prepareLookupData($dataRaw);
  }
}
