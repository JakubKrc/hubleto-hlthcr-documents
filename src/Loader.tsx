import React from 'react';
import App from '@hubleto/react-ui/core/App'
import Translator from '@hubleto/react-ui/core/Translator';
import FormCustomizer from '@hubleto/react-ui/core/FormCustomizer';
import { type FormMeta } from '@hubleto/react-ui/components/fc/FormInterfaces';
import TableDocuments from './Components/FC/TableDocuments'

const parentApp = 'Hubleto/App/External/WaiBlue/HlthcrDocuments';
const T = new Translator(parentApp + '/Loader', 'Loader');

// "Dokumenty" tab in the customer form: documents of the client and of its branches.
function mountCustomerDocumentsTab() {
  return {
    title: T.translate('Documents'),
    content: (form: FormMeta) => {
      if (form.id <= 0) return <div className='alert alert-info'>{T.translate('Save the client first.')}</div>;
      return <TableDocuments uid={form.uid + '_table_documents'} parentForm={form} idCustomer={form.id} />;
    },
  };
}

// "Dokumenty" tab in the branch form (HlthcrBranches); new documents get the branch and its client.
function mountBranchDocumentsTab() {
  return {
    title: T.translate('Documents'),
    content: (form: FormMeta) => {
      if (form.id <= 0) return <div className='alert alert-info'>{T.translate('Save the branch first.')}</div>;
      const idCustomer = Number(form.originalRecord?.id_customer ?? 0);
      return <TableDocuments
        uid={form.uid + '_table_documents'}
        parentForm={form}
        idBranch={form.id}
        formDefaultValues={{id_branch: form.id, id_customer: idCustomer}}
      />;
    },
  };
}

// "Dokumenty" tab in the audit form (HlthcrAudits).
function mountAuditDocumentsTab() {
  return {
    title: T.translate('Documents'),
    content: (form: FormMeta) => {
      if (form.id <= 0) return <div className='alert alert-info'>{T.translate('Save the audit first.')}</div>;
      const idBranch = Number(form.originalRecord?.id_branch ?? 0);
      const idCustomer = Number(form.originalRecord?.BRANCH?.id_customer ?? 0);
      return <TableDocuments
        uid={form.uid + '_table_documents'}
        parentForm={form}
        idAudit={form.id}
        formDefaultValues={{id_audit: form.id, id_branch: idBranch, id_customer: idCustomer}}
      />;
    },
  };
}

class HlthcrDocumentsApp extends App {
  init() {
    super.init();
    globalThis.hubleto.registerReactComponent('HlthcrDocumentsTableDocuments', TableDocuments);

    FormCustomizer.addTab('FormCustomer', 'hlthcr-documents', mountCustomerDocumentsTab);
    FormCustomizer.addTab('FormBranch', 'hlthcr-documents', mountBranchDocumentsTab);
    FormCustomizer.addTab('FormAudit', 'hlthcr-documents', mountAuditDocumentsTab);
  }
}

globalThis.hubleto.registerApp(parentApp, new HlthcrDocumentsApp());
