import { LoadingSkeleton } from '@centreon/ui';
import { DataTable, PageHeader, PageLayout } from '@centreon/ui/components';

import { PrimitiveAtom, useAtom, useSetAtom } from 'jotai';
import { equals, isNil, isNotEmpty, or } from 'ramda';
import { JSX, useLayoutEffect } from 'react';
import { useSearchParams } from 'react-router';

import { ConfigurationBase } from '../models';
import { formStateAtom } from './atoms';
import { DeleteDialog, DuplicateDialog } from './Dialogs';
import useCoutChangedFilters from './Filters/AdvancedFilters/useCoutChangedFilters';
import { Listing } from './Listing';
import useLoadData from './Listing/useLoadData';
import { Modal } from './Modal';
import Navbar from './NavBar';
import { PanelLayout } from './Panel';
import useSyncFormStateWithUrl from './useSyncFormStateWithUrl';

interface WelcomePageProps {
  labels: ConfigurationBase<unknown>['labels']['welcomePage'];
  dataTestId: string;
  onCreate: () => void;
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  filtersAtom: PrimitiveAtom<any>;
  filtersAtomKey: string;
  isWelcomePageDisplayedAtom: PrimitiveAtom<boolean>;
  hasWriteAccess: boolean;
}

const WelcomePage = ({
  labels,
  dataTestId,
  onCreate,
  filtersAtom,
  filtersAtomKey,
  isWelcomePageDisplayedAtom,
  hasWriteAccess
}: WelcomePageProps) => {
  const { isLoading, data } = useLoadData({ filtersAtom, filtersAtomKey });

  const setIsWelcomePageDisplayed = useSetAtom(isWelcomePageDisplayedAtom);
  const { isClear } = useCoutChangedFilters({ filtersAtom });

  useLayoutEffect(() => {
    if (!isLoading && (!isClear || (isClear && isNotEmpty(data?.result)))) {
      setIsWelcomePageDisplayed(false);
    }
  }, [isLoading]);

  if (isLoading && isNil(data)) {
    return <LoadingSkeleton />;
  }

  return (
    <DataTable.EmptyState
      aria-label="create"
      canCreate={hasWriteAccess}
      data-testid={dataTestId}
      labels={labels}
      onCreate={onCreate}
    />
  );
};

const Page = <TFilters,>({
  columns,
  resourceType,
  form,
  actions,
  labels,
  selectedColumnIdsAtom,
  filtersAtom,
  filtersAtomKey,
  isWelcomePageDisplayedAtom,
  navbar,
  formVariant = 'modal',
  formPanelWidth
}: Pick<
  ConfigurationBase<TFilters>,
  | 'columns'
  | 'form'
  | 'resourceType'
  | 'actions'
  | 'labels'
  | 'selectedColumnIdsAtom'
  | 'filtersAtom'
  | 'filtersAtomKey'
  | 'isWelcomePageDisplayedAtom'
  | 'navbar'
  | 'formVariant'
  | 'formPanelWidth'
>): JSX.Element => {
  const [, setSearchParams] = useSearchParams();

  const setFormState = useSetAtom(formStateAtom);
  const [isWelcomePageDisplayed, setIsWelcomePageDisplayed] = useAtom(
    isWelcomePageDisplayedAtom
  );

  const { isLoading, data } = useLoadData({ filtersAtom, filtersAtomKey });

  const hasFormAccess = or(!!actions?.edit, !!actions?.viewDetails);
  const isFormInPanel = equals(formVariant, 'panel');

  useSyncFormStateWithUrl({ hasFormAccess });

  const openCreateForm = (): void => {
    setSearchParams({ mode: 'add' });

    setFormState({ id: null, isOpen: true, mode: 'add', resource: null });

    setIsWelcomePageDisplayed(false);
  };

  const listing = (
    <DataTable
      isEmpty={isWelcomePageDisplayed}
      variant={isWelcomePageDisplayed ? 'grid' : 'listing'}
    >
      {isWelcomePageDisplayed ? (
        <WelcomePage
          dataTestId={`create-${resourceType}`}
          filtersAtom={filtersAtom}
          filtersAtomKey={filtersAtomKey}
          hasWriteAccess={!!actions?.edit}
          isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
          labels={labels.welcomePage}
          onCreate={openCreateForm}
        />
      ) : (
        <Listing<TFilters>
          actions={actions}
          columns={columns}
          data={data}
          filtersAtom={filtersAtom}
          filtersAtomKey={filtersAtomKey}
          hasWriteAccess={!!actions?.edit}
          isLoading={isLoading}
          selectedColumnIdsAtom={selectedColumnIdsAtom}
        />
      )}
    </DataTable>
  );

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Title title={labels.title} />
          </PageHeader.Main>
          {!!navbar && (
            <PageHeader.Actions>
              <Navbar navbar={navbar} />
            </PageHeader.Actions>
          )}
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        {isFormInPanel && hasFormAccess ? (
          <PanelLayout
            form={form}
            hasWriteAccess={!!actions?.edit}
            width={formPanelWidth}
          >
            {listing}
          </PanelLayout>
        ) : (
          listing
        )}
      </PageLayout.Body>
      {hasFormAccess && !isFormInPanel && (
        <Modal form={form} hasWriteAccess={!!actions?.edit} />
      )}
      {actions?.delete && <DeleteDialog />}
      {actions?.duplicate && <DuplicateDialog />}
    </PageLayout>
  );
};

export default Page;
