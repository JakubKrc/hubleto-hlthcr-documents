<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments\EventListeners;

use Hubleto\App\External\WaiBlue\HlthcrCore\Services\ProtectedFile;
use Hubleto\App\External\WaiBlue\HlthcrAudits\Models\Audit;
use Hubleto\App\External\WaiBlue\HlthcrDocuments\Models\Document;
use Hubleto\Framework\Interfaces\ModelInterface;

/**
 * Legacy: deleting an audit deletes its documents, including their files.
 * The database removes the document rows with the audit; their files are removed only after the audit is gone.
 */
class AuditChanges extends \Hubleto\Framework\EventListener implements \Hubleto\Framework\Interfaces\EventListenerInterface
{
  /** @var array<int, string[]> Files of the documents of audits being deleted, by audit id */
  private array $filesByAudit = [];

  public function onModelBeforeDelete(ModelInterface $model, int $id): void
  {
    if (!($model instanceof Audit)) return;

    /** @var Document */
    $mDocument = $this->getModel(Document::class);
    $this->filesByAudit[$id] = $mDocument->record->where('id_audit', $id)->pluck('file')->toArray();
  }

  public function onModelAfterDelete(ModelInterface $model, int $id): void
  {
    if (!($model instanceof Audit)) return;

    /** @var ProtectedFile */
    $protectedFile = $this->getService(ProtectedFile::class);
    foreach ($this->filesByAudit[$id] ?? [] as $file) {
      $protectedFile->delete((string) $file);
    }
    unset($this->filesByAudit[$id]);
  }
}
