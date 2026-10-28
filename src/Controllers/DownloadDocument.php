<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments\Controllers;

use Hubleto\App\External\WaiBlue\HlthcrCore\Services\ProtectedFile;
use Hubleto\App\External\WaiBlue\HlthcrDocuments\Models\Document;

/**
 * Downloads the file of a work document (legacy "Zobraziť dokument") after checking the user's access.
 */
class DownloadDocument extends \Hubleto\Erp\Controller
{
  // Raw file or plain-text answer, without the Hubleto desktop around it.
  public int $returnType = self::RETURN_TYPE_STRING;

  // The legacy downloads did not check access; this one needs the Documents app and checks the visibility.

  public function renderString(): string
  {
    /** @var ProtectedFile $protectedFile */
    $protectedFile = $this->getService(ProtectedFile::class);
    $idDocument = $this->router()->urlParamAsInteger('idDocument');

    // Read through prepareReadQuery, so documents of foreign clients are not found at all.
    $document = $this->getModel(Document::class)->record->prepareReadQuery()
      ->where('hlthcr_documents.id', $idDocument)
      ->first()
    ;
    if (!$document) {
      return $protectedFile->refuse(404, $this->translate('The document does not exist.'));
    }

    if ((string) $document->file == '') {
      return $protectedFile->refuse(404, $this->translate('No file is uploaded.'));
    }

    return $protectedFile->send((string) $document->file);
  }
}
