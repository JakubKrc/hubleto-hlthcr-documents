<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments;

use Hubleto\App\Community\Workflow\Models\WorkflowStep;
use Hubleto\App\External\WaiBlue\HlthcrCore\Services\CalendarAuthor;

/**
 * Work documents in the Hubleto calendar, one all-day event on the creation date (as in the legacy calendar).
 * Events are derived from the documents, so there is no event table to keep in sync.
 */
class Calendar extends \Hubleto\App\Community\Calendar\Calendar
{
  // Legacy calendar colour of documents (yellow).
  const COLOR = '#e0a800';

  public string $translationContext = 'hubleto-app-external-waiblue-hlthcrdocuments-loader';
  public string $translationContextInner = 'Calendar';

  public function getCalendarConfig(): array
  {
    return [
      'position' => 7,
      'color' => self::COLOR,
      'title' => $this->translate('Work documents'),
      'icon' => 'fas fa-file-lines',
    ];
  }

  public function loadEvent(int $id): array
  {
    $document = $this->getModel(Models\Document::class)->record->prepareReadQuery()
      ->where('hlthcr_documents.id', $id)
      ->first()
    ;
    if (!$document) return [];
    return $document->toArray();
  }

  public function loadEvents(string $dateStart, string $dateEnd, array $filter = []): array
  {
    $query = $this->getModel(Models\Document::class)->record->prepareReadQuery()
      ->where('hlthcr_documents.date_created', '>=', $dateStart)
      ->where('hlthcr_documents.date_created', '<=', $dateEnd)
    ;

    $idAuthor = $this->getService(CalendarAuthor::class)->getAuthorIdToShow($filter);
    if ($idAuthor > 0) {
      $query = $query->where('hlthcr_documents.id_author', $idAuthor);
    }

    $doneStepIds = $this->getModel(WorkflowStep::class)->record
      ->whereIn('tag', Loader::DONE_STEP_TAGS)
      ->pluck('id')
      ->toArray()
    ;

    // Hubleto's calendar filter "not done" is 1, "done" is 2.
    $completedFilter = (int) ($filter['fCompleted'] ?? 0);
    if ($completedFilter == 1) {
      $query = $query->where(function ($notDoneQuery) use ($doneStepIds) {
        $notDoneQuery->whereNotIn('hlthcr_documents.id_workflow_step', $doneStepIds)
          ->orWhereNull('hlthcr_documents.id_workflow_step');
      });
    }
    if ($completedFilter == 2) $query = $query->whereIn('hlthcr_documents.id_workflow_step', $doneStepIds);

    $documents = $query->get();

    $events = [];
    foreach ($documents as $document) {
      $isDone = in_array((int) $document->id_workflow_step, $doneStepIds);
      $events[] = [
        'id' => (int) $document->id,
        'start' => $document->date_created,
        'allDay' => true,
        'title' => $this->translate('Document - {{ name }}', ['name' => $document->name]),
        'color' => self::COLOR,
        'source' => 'hlthcr-documents',
        'id_owner' => $document->id_author,
        'completed' => $isDone ? 1 : 0,
        'url' => 'hlthcr-documents/documents/' . $document->id,
      ];
    }

    return $events;
  }
}
