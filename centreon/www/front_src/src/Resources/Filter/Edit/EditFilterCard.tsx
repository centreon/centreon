import { KeyboardEvent, useState } from 'react';

import { useFormik } from 'formik';
import { useAtom, useSetAtom } from 'jotai';
import { all, any, equals, findIndex, omit, reject, update } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import DeleteIcon from '@mui/icons-material/Delete';

import {
  ConfirmDialog,
  ContentWithCircularLoading,
  IconButton,
  TextField,
  useRequest,
  useSnackbar
} from '@centreon/ui';

import { object, string } from 'yup';
import {
  labelAskDelete,
  labelCancel,
  labelDelete,
  labelFilter,
  labelFilterDeleted,
  labelFilterUpdated,
  labelName,
  labelNameCannotBeEmpty
} from '../../translatedLabels';
import { deleteFilter, updateFilter } from '../api';
import {
  appliedFilterAtom,
  currentFilterAtom,
  customFiltersAtom
} from '../filterAtoms';
import { Filter, newFilter } from '../models';

const useStyles = makeStyles()((theme) => ({
  filterCard: {
    alignItems: 'center',
    display: 'grid',
    gridAutoFlow: 'column',
    gridGap: theme.spacing(2),
    gridTemplateColumns: 'auto 1fr'
  }
}));

interface Props {
  filter: Filter;
}

const areFilterIdsEqual =
  (filter: Filter) =>
  (filterToCompare: Filter): boolean =>
    equals(Number(filter.id), Number(filterToCompare.id));

const EditFilterCard = ({ filter }: Props): JSX.Element => {
  const { classes } = useStyles();

  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();

  const [deleting, setDeleting] = useState(false);

  const {
    sendRequest: sendUpdateFilterRequest,
    sending: sendingUpdateFilterRequest
  } = useRequest({
    request: updateFilter
  });

  const {
    sendRequest: sendDeleteFilterRequest,
    sending: sendingDeleteFilterRequest
  } = useRequest({
    request: deleteFilter
  });

  const [currentFilter, setCurrentFilter] = useAtom(currentFilterAtom);
  const [customFilters, setCustomFilters] = useAtom(customFiltersAtom);
  const setAppliedFilter = useSetAtom(appliedFilterAtom);

  const { name, id } = filter;

  const validationSchema = object().shape({
    name: string().required(t(labelNameCannotBeEmpty))
  });

  const form = useFormik({
    enableReinitialize: true,
    initialValues: {
      name
    },
    onSubmit: (values) => {
      const updatedFilter = { ...filter, name: values.name };

      sendUpdateFilterRequest({
        filter: omit(['id'], updatedFilter),
        id: updatedFilter.id
      }).then(() => {
        showSuccessMessage(t(labelFilterUpdated));

        if (equals(updatedFilter.id, currentFilter.id)) {
          setCurrentFilter(updatedFilter);
        }

        const index = findIndex(areFilterIdsEqual(filter), customFilters);

        setCustomFilters(update(index, updatedFilter, customFilters));
      });
    },
    validationSchema
  });

  const askDelete = (): void => {
    setDeleting(true);
  };

  const confirmDelete = (): void => {
    setDeleting(false);

    sendDeleteFilterRequest(filter).then(() => {
      showSuccessMessage(t(labelFilterDeleted));

      if (areFilterIdsEqual(filter)(currentFilter)) {
        setCurrentFilter({ ...filter, ...newFilter });
        setAppliedFilter({ ...filter, ...newFilter });
      }

      setCustomFilters(reject(areFilterIdsEqual(filter), customFilters));
    });
  };

  const cancelDelete = (): void => {
    setDeleting(false);
  };

  const sendingRequest = any(equals(true), [
    sendingDeleteFilterRequest,
    sendingUpdateFilterRequest
  ]);

  const canRename = all(equals(true), [form.isValid, form.dirty]);

  const rename = (): void => {
    if (!canRename) {
      return;
    }

    form.submitForm();
  };

  const renameOnEnterKey = (event: KeyboardEvent<HTMLDivElement>): void => {
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
        <IconButton
          aria-label={t(labelDelete)}
          size="large"
          title={t(labelDelete)}
          onClick={askDelete}
        >
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

export default EditFilterCard;
