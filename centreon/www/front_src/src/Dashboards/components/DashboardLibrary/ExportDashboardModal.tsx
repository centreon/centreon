import { useState } from 'react';

import { useAtom, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import { dashboardsToExportAtom } from '../../atoms';
import { labelCancel } from '../../translatedLabels';

import { selectedRowsAtom } from './DashboardListing/atom';

import { centreonBaseURL } from 'packages/ui/src';

const dimensions = { height: screen.height, width: screen.width };

const ExportDashboardModal = (): JSX.Element => {
  const { t } = useTranslation();
  const { protocol, hostname, port } = window.location;

  const [isLoading, setIsLoading] = useState(false);

  const [dashboardsToExport, setDashboardsToExport] = useAtom(
    dashboardsToExportAtom
  );
  const setSelectedRows = useSetAtom(selectedRowsAtom);

  const confirm = async () => {
    setIsLoading(true);

    const dimensionsParam = encodeURIComponent(JSON.stringify(dimensions));
    const dashboardsParam = encodeURIComponent(
      JSON.stringify(dashboardsToExport)
    );

    const baseUrl = encodeURIComponent(
      JSON.stringify(`${protocol}//${hostname}:${port}${centreonBaseURL}`)
    );

    const url = `/export-pdf?dimensions=${dimensionsParam}&dashboards=${dashboardsParam}&baseUrl=${baseUrl}`;

    try {
      const response = await fetch(`http://localhost:3002${url}`);
      const blob = await response.blob();
      const downloadUrl = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = downloadUrl;
      link.setAttribute('download', 'final.pdf');
      document.body.appendChild(link);
      link.click();
      link.parentNode.removeChild(link);
    } catch (error) {
      console.error('Error downloading PDF:', error);
    } finally {
      setIsLoading(false);
      setDashboardsToExport(null);
      setSelectedRows([]);
      close();
    }
  };

  const close = (): void => {
    setDashboardsToExport(null);
    setSelectedRows([]);
  };

  return (
    <Modal open={Boolean(dashboardsToExport)} onClose={close}>
      <Modal.Header>Export PDF</Modal.Header>
      <Modal.Body>
        Export dashboards{': '}
        {dashboardsToExport?.map(({ name }) => name)?.join(',')}
      </Modal.Body>
      {isLoading && (
        <div style={{ color: 'orange' }}>
          Please wait, the process may take few seconds
        </div>
      )}
      <Modal.Actions
        isDanger
        isLoading={isLoading}
        labels={{
          cancel: t(labelCancel),
          confirm: 'Export PDF'
        }}
        onCancel={close}
        onConfirm={confirm}
      />
    </Modal>
  );
};

export default ExportDashboardModal;
