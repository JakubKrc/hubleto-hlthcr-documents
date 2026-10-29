import React from 'react';
import Translator from '@hubleto/react-ui/core/Translator';
import Form, { FormMetaContext } from '@hubleto/react-ui/components/fc/Form';
import { type FormProps } from '@hubleto/react-ui/components/fc/FormInterfaces';
import Input from '@hubleto/react-ui/components/fc/FormComponents/Input';
import { useRecordField } from '@hubleto/react-ui/components/fc/FormRecordStore';

const componentName = 'FormDocument';
const parentApp = 'Hubleto/App/External/WaiBlue/HlthcrDocuments';
const T = new Translator(parentApp + '/Loader', 'Components/' + componentName);

// The branch dropdown offers only branches of the selected client (FC lookups do not send form values).
const InputBranchOfSelectedClient = () => {
  const idCustomer = useRecordField('id_customer', 0);
  return <Input
    key={'branch-of-client-' + idCustomer}
    field='id_branch'
    customInputProps={{myself: {customEndpointParams: {idCustomer: idCustomer}}}}
  />;
}

// The audit dropdown offers only audits of the selected branch, so it is shown once a branch is chosen.
const InputAuditOfSelectedBranch = () => {
  const idBranch = Number(useRecordField('id_branch', 0));
  if (idBranch <= 0) return null;
  return <Input
    key={'audit-of-branch-' + idBranch}
    field='id_audit'
    customInputProps={{myself: {customEndpointParams: {idBranch: idBranch}}}}
  />;
}

// The creation date stays as when the document was created (the server keeps it).
const TabDefault = () => {
  const form = React.useContext(FormMetaContext);
  const isSaved = form.id > 0;
  return <div className='grid grid-cols-1 md:grid-cols-2 gap-4'>
    <div>
      <Input field='name' />
      <Input field='id_customer' />
      <InputBranchOfSelectedClient />
      <InputAuditOfSelectedBranch />
      <Input field='date_created' readonly={isSaved} />
      <Input field='file' />
    </div>
    <div>
      <Input field='priority' />
      <Input field='id_author' />
    </div>
  </div>;
}

const FormDocument = (props: FormProps) => <Form
  componentName={componentName}
  parentApp={parentApp}
  model={parentApp + '/Models/Document'}
  urlSlug='hlthcr-documents/documents'
  title={{field: 'name', sub: T.translate('Work document')}}
  tabs={{default: {content: () => <TabDefault />}}}
  {...props}
/>;

export default FormDocument;
