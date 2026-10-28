<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments\Models\Migrations;

use Hubleto\Framework\Migration;

class Document_0001 extends Migration
{
  public function upgradeSchema(): void
  {
    $this->db->execute("CREATE TABLE IF NOT EXISTS `hlthcr_documents` (
      `id` int(8) primary key auto_increment,
      `name` varchar(255) NOT NULL,
      `id_customer` int(8) NULL DEFAULT NULL,
      `id_branch` int(8) NULL DEFAULT NULL,
      `id_audit` int(8) NULL DEFAULT NULL,
      `date_created` date NULL DEFAULT NULL,
      `file` varchar(255) NULL DEFAULT NULL,
      `priority` int(1) NOT NULL DEFAULT 3,
      `id_author` int(8) NULL DEFAULT NULL,
      `id_workflow` int(8) NULL DEFAULT NULL,
      `id_workflow_step` int(8) NULL DEFAULT NULL,
      INDEX `id_customer` (`id_customer`),
      INDEX `id_branch` (`id_branch`),
      INDEX `id_audit` (`id_audit`),
      INDEX `id_workflow_step` (`id_workflow_step`),
      INDEX `date_created` (`date_created`)
    ) ENGINE=InnoDB");
  }

  public function downgradeSchema(): void
  {
    $this->db->execute("DROP TABLE IF EXISTS `hlthcr_documents`");
  }

  public function upgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_documents` ADD CONSTRAINT `fk__hlthcr_documents__id_customer` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT");
    $this->db->execute("ALTER TABLE `hlthcr_documents` ADD CONSTRAINT `fk__hlthcr_documents__id_branch` FOREIGN KEY (`id_branch`) REFERENCES `hlthcr_branches` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT");
    $this->db->execute("ALTER TABLE `hlthcr_documents` ADD CONSTRAINT `fk__hlthcr_documents__id_audit` FOREIGN KEY (`id_audit`) REFERENCES `hlthcr_audits` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT");
  }

  public function downgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_documents` DROP FOREIGN KEY `fk__hlthcr_documents__id_customer`");
    $this->db->execute("ALTER TABLE `hlthcr_documents` DROP FOREIGN KEY `fk__hlthcr_documents__id_branch`");
    $this->db->execute("ALTER TABLE `hlthcr_documents` DROP FOREIGN KEY `fk__hlthcr_documents__id_audit`");
  }
}
