<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments;

use Hubleto\App\External\WaiBlue\HlthcrCore\Services\Roles;

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

    // Legacy calendar: administrators and managers see all document events, others only their own documents.
    $idAuthor = (int) ($filter['idUser'] ?? 0);
    $showsOnlyMyEvents = (int) ($filter['fOwnership'] ?? 0) == 1;
    $canSeeAllDocumentEvents = $this->getService(Roles::class)->isActiveUserAdministratorOrManager();
    if ($showsOnlyMyEvents || !$canSeeAllDocumentEvents) $idAuthor = $this->authProvider()->getUserId();
    if ($idAuthor > 0) {
      $query = $query->where('hlthcr_documents.id_author', $idAuthor);
    }

    $documents = $query->get();

    $events = [];
    foreach ($documents as $document) {
      $events[] = [
        'id' => (int) $document->id,
        'start' => $document->date_created,
        'allDay' => true,
        'title' => $this->translate('Document - {{ name }}', ['name' => $document->name]),
        'color' => self::COLOR,
        'source' => 'hlthcr-documents',
        'id_owner' => $document->id_author,
        'url' => 'hlthcr-documents/documents/' . $document->id,
      ];
    }

    return $events;
  }
}
