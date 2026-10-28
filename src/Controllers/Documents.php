<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments\Controllers;

class Documents extends \Hubleto\Erp\Controller
{
  public function prepareView(): void
  {
    parent::prepareView();
    $this->setView('@Hubleto:App:External:WaiBlue:HlthcrDocuments/Documents.twig');
  }
}
