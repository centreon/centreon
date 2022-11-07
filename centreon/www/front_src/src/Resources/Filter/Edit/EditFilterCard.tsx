<<<<<<< HEAD
import { KeyboardEvent, useState } from 'react';

import { useFormik } from 'formik';
import * as Yup from 'yup';
import { all, equals, any, reject, update, findIndex, omit } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useUpdateAtom } from 'jotai/utils';
import { useAtom } from 'jotai';

import DeleteIcon from '@mui/icons-material/Delete';
import makeStyles from '@mui/styles/makeStyles';
=======
import * as React from 'react';

import { useFormik } from 'formik';
import * as Yup from 'yup';
import {
  all,
  equals,
  any,
  reject,
  update,
  findIndex,
  propEq,
  omit,
} from 'ramda';
import { useTranslation } from 'react-i18next';

import DeleteIcon from '@material-ui/icons/Delete';
import { makeStyles } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

import {
  ContentWithCircularLoading,
  TextField,
  IconButton,
  useRequest,
  ConfirmDialog,
  useSnackbar,
} from '@centreon/ui';

import {
  labelDelete,
  labelAskDelete,
  labelCancel,
  labelFilterDeleted,
  labelFilterUpdated,
  labelName,
  labelFilter,
  labelNameCannotBeEmpty,
} from '../../translatedLabels';
import { updateFilter, deleteFilter } from '../api';
import { Filter, newFilter } from '../models';
<<<<<<< HEAD
import {
  appliedFilterAtom,
  currentFilterAtom,
  customFiltersAtom,
} from '../filterAtoms';
=======
import { ResourceContext, useResourceContext } from '../../Context';
import memoizeComponent from '../../memoizedComponent';
>>>>>>> centreon/dev-21.10.x

const useStyles = makeStyles((theme) => ({
  filterCard: {
    alignItems: 'center',
    display: 'grid',
    gridAutoFlow: 'column',
    gridGap: theme.spacing(2),
    gridTemplateColumns: 'auto 1fr',
  },
}));

<<<<<<< HEAD
interface Props {
  filter: Filter;
}

const areFilterIdsEqual =
  (filter: Filter) =>
  (filterToCompare: Filter): boolean =>
    equals(Number(filter.id), Number(filterToCompare.id));

const EditFilterCard = ({ filter }: Props): JSX.Element => {
=======
interface EditFilterCardProps {
  filter: Filter;
}

type Props = EditFilterCardProps &
  Pick<
    ResourceContext,
    | 'customFilters'
    | 'setCurrentFilter'
    | 'setCustomFilters'
    | 'setAppliedFilter'
    | 'currentFilter'
    | 'currentFilter'
  >;

const EditFilterCardContent = ({
  filter,
  currentFilter,
  customFilters,
  setCurrentFilter,
  setAppliedFilter,
  setCustomFilters,
}: Props): JSX.Element => {
>>>>>>> centreon/dev-21.10.x
  const classes = useStyles();

  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();

<<<<<<< HEAD
  const [deleting, setDeleting] = useState(false);
=======
  const [deleting, setDeleting] = React.useState(false);
>>>>>>> centreon/dev-21.10.x

  const {
    sendRequest: sendUpdateFilterRequest,
    sending: sendingUpdateFilterRequest,
  } = useRequest({
    request: updateFilter,
  });

  const {
    sendRequest: sendDeleteFilterRequest,
    sending: sendingDeleteFilterRequest,
  } = useRequest({
    request: deleteFilter,
  });

<<<<<<< HEAD
  const [currentFilter, setCurrentFilter] = useAtom(currentFilterAtom);
  const [customFilters, setCustomFilters] = useAtom(customFiltersAtom);
  const setAppliedFilter = useUpdateAtom(appliedFilterAtom);

=======
>>>>>>> centreon/dev-21.10.x
  const { name, id } = filter;

  const validationSchema = Yup.object().shape({
    name: Yup.string().required(t(labelNameCannotBeEmpty)),
  });

  const form = useFormik({
    enableReinitialize: true,
    initialValues: {
      name,
    },
    onSubmit: (values) => {
      const updatedFilter = { ...filter, name: values.name };

      sendUpdateFilterRequest({
        filter: omit(['id'], updatedFilter),
        id: updatedFilter.id,
      }).then(() => {
        showSuccessMessage(t(labelFilterUpdated));

        if (equals(updatedFilter.id, currentFilter.id)) {
          setCurrentFilter(updatedFilter);
        }

<<<<<<< HEAD
        const index = findIndex(areFilterIdsEqual(filter), customFilters);
=======
        const index = findIndex(propEq('id', updatedFilter.id), customFilters);
>>>>>>> centreon/dev-21.10.x

        setCustomFilters(update(index, updatedFilter, customFilters));
      });
    },
    validationSchema,
  });

  const askDelete = (): void => {
    setDeleting(true);
  };

  const confirmDelete = (): void => {
    setDeleting(false);

    sendDeleteFilterRequest(filter).then(() => {
      showSuccessMessage(t(labelFilterDeleted));

<<<<<<< HEAD
      if (areFilterIdsEqual(filter)(currentFilter)) {
=======
      if (equals(filter.id, currentFilter.id)) {
>>>>>>> centreon/dev-21.10.x
        setCurrentFilter({ ...filter, ...newFilter });
        setAppliedFilter({ ...filter, ...newFilter });
      }

<<<<<<< HEAD
      setCustomFilters(reject(areFilterIdsEqual(filter), customFilters));
=======
      setCustomFilters(reject(equals(filter), customFilters));
>>>>>>> centreon/dev-21.10.x
    });
  };

  const cancelDelete = (): void => {
    setDeleting(false);
  };

  const sendingRequest = any(equals(true), [
    sendingDeleteFilterRequest,
    sendingUpdateFilterRequest,
  ]);

  const canRename = all(equals(true), [form.isValid, form.dirty]);

  const rename = (): void => {
    if (!canRename) {
      return;
    }

    form.submitForm();
  };

<<<<<<< HEAD
  const renameOnEnterKey = (event: KeyboardEvent<HTMLDivElement>): void => {
=======
  const renameOnEnterKey = (event: React.KeyboardEvent): void => {
>>>>>>> centreon/dev-21.10.x
    const enterKeyPressed = event.keyCode === 13;

    if (enterKeyPressed) {
      rename();
    }
  };

  return (
    <div className={classes.filterCard}>
      <ContentWithCircularLoading
        alignCenter={false}
        loading={sendingRequest}
        loadingIndicatorSize={24}
      >
<<<<<<< HEAD
        <IconButton
          aria-label={t(labelDelete)}
          size="large"
          title={t(labelDelete)}
          onClick={askDelete}
        >
=======
        <IconButton title={t(labelDelete)} onClick={askDelete}>
>>>>>>> centreon/dev-21.10.x
          <DeleteIcon fontSize="small" />
        </IconButton>
      </ContentWithCircularLoading>
      <TextField
        transparent
        ariaLabel={`${t(labelFilter)}-${id}-${t(labelName)}`}
        error={form.errors.name}
        value={form.values.name}
        onBlur={rename}
        onChange={form.handleChange('name') as (event) => void}
        onKeyDown={renameOnEnterKey}
      />

      {deleting && (
        <ConfirmDialog
          open
          labelCancel={t(labelCancel)}
          labelConfirm={t(labelDelete)}
          labelTitle={t(labelAskDelete)}
          onCancel={cancelDelete}
          onConfirm={confirmDelete}
        />
      )}
    </div>
  );
};

<<<<<<< HEAD
=======
const memoProps = ['filter', 'currentFilter', 'customFilters'];

const MemoizedEditFilterCardContent = memoizeComponent<Props>({
  Component: EditFilterCardContent,
  memoProps,
});

const EditFilterCard = ({ filter }: EditFilterCardProps): JSX.Element => {
  const {
    setCurrentFilter,
    filterWithParsedSearch,
    setCustomFilters,
    customFilters,
    setAppliedFilter,
  } = useResourceContext();

  return (
    <MemoizedEditFilterCardContent
      currentFilter={filterWithParsedSearch}
      customFilters={customFilters}
      filter={filter}
      setAppliedFilter={setAppliedFilter}
      setCurrentFilter={setCurrentFilter}
      setCustomFilters={setCustomFilters}
    />
  );
};

>>>>>>> centreon/dev-21.10.x
export default EditFilterCard;
