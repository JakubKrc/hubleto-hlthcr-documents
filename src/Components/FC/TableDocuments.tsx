import Table from '@hubleto/react-ui/components/fc/Table'
import { type TableMeta, type TableProps } from '@hubleto/react-ui/components/fc/TableInterfaces'
import FormDocument from './FormDocument'

interface TableDocumentsProps extends TableProps {
  idCustomer?: number,
  idBranch?: number,
  idAudit?: number,
}

const componentName = 'TableDocuments'
const parentApp = 'Hubleto/App/External/WaiBlue/HlthcrDocuments'

// Used as the main list and as a tab of the customer, branch and audit forms.
const TableDocuments = (props: TableDocumentsProps) => {
  const idCustomer = props.idCustomer ?? 0;
  const idBranch = props.idBranch ?? 0;
  const idAudit = props.idAudit ?? 0;

  const formDefaultValues: Record<string, number> = {};
  if (idCustomer > 0) formDefaultValues.id_customer = idCustomer;
  if (idBranch > 0) formDefaultValues.id_branch = idBranch;
  if (idAudit > 0) formDefaultValues.id_audit = idAudit;

  return <Table
    componentName={componentName}
    parentApp={parentApp}
    model={parentApp + '/Models/Document'}
    baseUrlSlug='hlthcr-documents/documents'
    endpointParams={{idCustomer: idCustomer, idBranch: idBranch, idAudit: idAudit}}
    formDefaultValues={formDefaultValues}
    formModalProps={{type: 'right wide'}}
    renderForm={(table: TableMeta) => <FormDocument {...table.getDefaultFormProps()} />}
    {...props}
  />;
}

export default TableDocuments;
