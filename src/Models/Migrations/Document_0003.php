<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments\Models\Migrations;

use Hubleto\Framework\Migration;

// Foreign keys of the author and the workflow state (models and lookups review B6).
class Document_0003 extends Migration
{
  public function upgradeSchema(): void
  {
    // Rows created on the command line (demo data) had no user, stored as 0.
    $this->db->execute("UPDATE `hlthcr_documents` SET `id_author` = NULL WHERE `id_author` = 0");
  }

  public function downgradeSchema(): void
  {
  }

  public function upgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_documents` ADD CONSTRAINT `fk__hlthcr_documents__id_author` FOREIGN KEY (`id_author`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
    $this->db->execute("ALTER TABLE `hlthcr_documents` ADD CONSTRAINT `fk__hlthcr_documents__id_workflow` FOREIGN KEY (`id_workflow`) REFERENCES `workflows` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
    $this->db->execute("ALTER TABLE `hlthcr_documents` ADD CONSTRAINT `fk__hlthcr_documents__id_workflow_step` FOREIGN KEY (`id_workflow_step`) REFERENCES `workflow_steps` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
  }

  public function downgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_documents` DROP FOREIGN KEY `fk__hlthcr_documents__id_author`");
    $this->db->execute("ALTER TABLE `hlthcr_documents` DROP FOREIGN KEY `fk__hlthcr_documents__id_workflow`");
    $this->db->execute("ALTER TABLE `hlthcr_documents` DROP FOREIGN KEY `fk__hlthcr_documents__id_workflow_step`");
  }
}
