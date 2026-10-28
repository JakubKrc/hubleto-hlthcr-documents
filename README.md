# Hlthcr Documents

Working documents of clients, branches and audits with workflow states, priority and protected download.

Hubleto app of the Hlthcr set (occupational health service, Slovakia). Namespace: `Hubleto\App\External\WaiBlue\HlthcrDocuments`.
Needs: `wai-blue/hubleto-hlthcr-core`, `wai-blue/hubleto-hlthcr-branches`, `wai-blue/hubleto-hlthcr-audits`.

## Install into a Hubleto project

```bash
composer config repositories.wai-blue/hubleto-hlthcr-documents vcs <repository-url>
composer require wai-blue/hubleto-hlthcr-documents:dev-main
php hubleto app install "Hubleto\App\External\WaiBlue\HlthcrDocuments"
npm run build
```
