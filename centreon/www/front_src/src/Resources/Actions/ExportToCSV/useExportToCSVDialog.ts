import { useState } from 'react';

import { useAtom, useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { equals, filter, includes, pipe, pluck } from 'ramda';

import { featureFlagsDerivedAtom } from '@centreon/ui-context';

import {
  isExportToCSVDialogOpenAtom,
  selectedVisualizationAtom
} from '../actionsAtoms';
import {
  limitAtom,
  pageAtom,
  selectedColumnIdsAtom
} from '../../Listing/listingAtoms';
import { getColumns } from '../../Listing/columns';

import { ColumnsOption, PagesOption } from './models';
import useExportToCSVRequest from './useExportToCSVRequest';

interface Props {
  changeExportedColumns: (event) => void;
  changeExportedPages: (event) => void;
  close: () => void;
  columnsToExport: ColumnsOption;
  exportToCSV: () => void;
  isOpen: boolean;
  loading: boolean;
  pagesToExport: PagesOption;
}

const useExportToCSVDialog = (): Props => {
  const { t } = useTranslation();
  const [pagesToExport, setPagesToExport] = useState(PagesOption.Current);
  const [columnsToExport, setColumnsToExport] = useState(ColumnsOption.Visible);

  const [isOpen, openDialog] = useAtom(isExportToCSVDialogOpenAtom);

  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const featureFlags = useAtomValue(featureFlagsDerivedAtom);
  const visualization = useAtomValue(selectedVisualizationAtom);
  const selectedColumnIds = useAtomValue(selectedColumnIdsAtom);

  const allColumns = getColumns({
    featureFlags,
    t,
    visualization
  });

  const columns = equals(columnsToExport, ColumnsOption.All)
    ? pluck('label', allColumns)
    : pipe(
        filter(({ id }) => includes(id, selectedColumnIds)),
        pluck('label')
      )(allColumns);

  const { loading, submit } = useExportToCSVRequest({
    columns,
    limit: equals(pagesToExport, PagesOption.All) ? undefined : limit,
    page: equals(pagesToExport, PagesOption.All) ? undefined : page
  });

  const changeExportedPages = (
    event: React.ChangeEvent<HTMLInputElement>
  ): void => {
    setPagesToExport(event.target.value as PagesOption);
  };

  const changeExportedColumns = (
    event: React.ChangeEvent<HTMLInputElement>
  ): void => {
    setColumnsToExport(event.target.value as ColumnsOption);
  };

  const close = (): void => openDialog(false);

  const exportToCSV = (): void => {
    submit();
    close();
  };

  return {
    changeExportedColumns,
    changeExportedPages,
    close,
    columnsToExport,
    exportToCSV,
    isOpen,
    loading,
    pagesToExport
  };
};

export default useExportToCSVDialog;
