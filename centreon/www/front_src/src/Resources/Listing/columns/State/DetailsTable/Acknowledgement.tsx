import parse from 'html-react-parser';
import DOMPurify from 'dompurify';
import { useTranslation } from 'react-i18next';

import { ColumnType, useLocaleDateTimeFormat } from '@centreon/ui';

import {
  labelAuthor,
  labelComment,
  labelEntryTime,
  labelPersistent,
  labelSticky
} from '../../../../translatedLabels';
import useStyles from '../State.styles';

import DetailsTable, { DetailsTableProps, getYesNoLabel } from '.';

interface AcknowledgementDetails {
  author_name: string;
  comment: string;
  entry_time: Date | string;
  id: number;
  is_persistent_comment: boolean;
  is_sticky: boolean;
}

const Comment = ({
  comment
}: Pick<AcknowledgementDetails, 'comment'>): JSX.Element => {
  const { classes } = useStyles();

  return (
    <span className={classes.comment}>
      {parse(DOMPurify.sanitize(comment))}
    </span>
  );
};

const AcknowledgementDetailsTable = ({
  endpoint
}: Pick<DetailsTableProps, 'endpoint'>): JSX.Element => {
  const { t } = useTranslation();

  const { toDateTime } = useLocaleDateTimeFormat();

  const columns = [
    {
      getContent: ({ author_name }): string => author_name,
      id: 'author',
      label: t(labelAuthor),
      type: ColumnType.string,
      width: 100
    },
    {
      getContent: ({ entry_time }): string => toDateTime(entry_time),
      id: 'entry_time',
      label: t(labelEntryTime),
      type: ColumnType.string,
      width: 150
    },
    {
      getContent: ({ is_persistent_comment }): string =>
        t(getYesNoLabel(is_persistent_comment)),
      id: 'is_persistent',
      label: t(labelPersistent),
      type: ColumnType.string,
      width: 100
    },
    {
      getContent: ({ is_sticky }): string => t(getYesNoLabel(is_sticky)),
      id: 'is_sticky',
      label: t(labelSticky),
      type: ColumnType.string,
      width: 100
    },

    {
      getContent: Comment,
      id: 'comment',
      label: t(labelComment),
      type: ColumnType.string,
      width: 250
    }
  ];

  return (
    <DetailsTable<AcknowledgementDetails>
      columns={columns}
      endpoint={endpoint}
    />
  );
};

export default AcknowledgementDetailsTable;
