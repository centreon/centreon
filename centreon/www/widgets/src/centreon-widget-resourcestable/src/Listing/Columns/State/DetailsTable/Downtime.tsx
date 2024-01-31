import parse from 'html-react-parser';
import DOMPurify from 'dompurify';
import { useTranslation } from 'react-i18next';

import { ColumnType, useLocaleDateTimeFormat } from '@centreon/ui';

import {
  labelAuthor,
  labelFixed,
  labelStartTime,
  labelEndTime,
  labelComment
} from '../../../translatedLabels';
import useStyles from '../State.styles';

import DetailsTable, { getYesNoLabel } from '.';

interface DowntimeDetails {
  author_name: string;
  comment: string;
  end_time: Date | string;
  id: number;
  is_fixed: boolean;
  start_time: Date | string;
}

const Comment = ({
  comment
}: Pick<DowntimeDetails, 'comment'>): JSX.Element => {
  const { classes } = useStyles();

  return (
    <span className={classes.comment}>
      {parse(DOMPurify.sanitize(comment))}
    </span>
  );
};

const EndTime = ({
  end_time
}: Pick<DowntimeDetails, 'end_time'>): JSX.Element => {
  const { toDateTime } = useLocaleDateTimeFormat();

  return <span>{toDateTime(end_time)}</span>;
};

interface Props {
  endpoint: string;
}

const DowntimeDetailsTable = ({ endpoint }: Props): JSX.Element => {
  const { classes } = useStyles();
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
      getContent: ({ is_fixed }): string => t(getYesNoLabel(is_fixed)),
      id: 'is_fixed',
      label: t(labelFixed),
      type: ColumnType.string,
      width: 100
    },
    {
      getContent: ({ start_time }): string => toDateTime(start_time),
      id: 'start_time',
      label: t(labelStartTime),
      type: ColumnType.string,
      width: 150
    },
    {
      getContent: EndTime,
      id: 'end_time',
      label: t(labelEndTime),
      type: ColumnType.string,
      width: 150
    },

    {
      className: classes.comment,
      getContent: Comment,
      id: 'comment',
      label: t(labelComment),
      type: ColumnType.component,
      width: 250
    }
  ];

  return (
    <DetailsTable<DowntimeDetails> columns={columns} endpoint={endpoint} />
  );
};

export default DowntimeDetailsTable;
