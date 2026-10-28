<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments;

/**
 * Work documents on the Workflow kanban board, grouped by state.
 */
class Workflow extends \Hubleto\App\Community\Workflow\Workflow
{
  public function loadItems(int $idWorkflow, array $filters): array
  {
    /** @var Models\Document */
    $mDocument = $this->getModel(Models\Document::class);
    $documents = $mDocument->record->prepareReadQuery()
      ->where('hlthcr_documents.id_workflow', $idWorkflow)
      ->with(['CUSTOMER'])
      ->get()
      ->toArray()
    ;

    $items = [];
    foreach ($documents as $document) {
      $document['_WORKFLOW_ITEM_TITLE'] = $document['name'];
      $document['_WORKFLOW_ITEM_SUBTITLE'] = $document['CUSTOMER']['name'] ?? '';
      $document['_DETAIL_URL'] = 'hlthcr-documents/documents/' . $document['id'];
      $document['_DETAIL_VIEW'] = '@Hubleto:App:Community:Workflow/WorkflowItemDetail.twig';
      $items[] = $document;
    }

    return $items;
  }
}
