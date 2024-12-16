import hljs from 'highlight.js/lib/core';

import bash from 'highlight.js/lib/languages/bash';
import json from 'highlight.js/lib/languages/json';
import php from 'highlight.js/lib/languages/php';
import yaml from 'highlight.js/lib/languages/yaml';

import ContentCopy from '@mui/icons-material/ContentCopy';
import parse from 'html-react-parser';
import { useTranslation } from 'react-i18next';
import { useCopyToClipboard } from '../../utils';
import { IconButton } from '../Button';
import { useCopyCommandStyle } from './CopyCommand.styles';
import {
  labelCommandCopied,
  labelFailedToCopyCommand
} from './translatedLabels';

hljs.registerLanguage('bash', bash);
hljs.registerLanguage('shell', bash);
hljs.registerLanguage('yaml', yaml);
hljs.registerLanguage('yml', yaml);
hljs.registerLanguage('json', json);
hljs.registerLanguage('php', php);

hljs.addPlugin({
  'after:highlight': (result) => {
    const leadingSpaces = result.value.match(/\n\s+/g);

    leadingSpaces?.forEach((leadingSpace) => {
      const sanitizedLeadingSpace = leadingSpace.replace(/\s/g, '&nbsp;');
      result.value = result.value.replace(
        leadingSpace,
        `\n${sanitizedLeadingSpace}`
      );
    });
  }
});

export interface CopyCommandProps {
  text: string;
  commandToCopy?: string;
  language: string;
}

const CopyCommand = ({ text, commandToCopy, language }: CopyCommandProps) => {
  const { t } = useTranslation();
  const { classes, cx } = useCopyCommandStyle();

  const { copy } = useCopyToClipboard({
    successMessage: t(labelCommandCopied),
    errorMessage: t(labelFailedToCopyCommand)
  });

  const copyCommand = (): Promise<void> | undefined | '' =>
    commandToCopy && copy(commandToCopy);

  const highlightedText = hljs.highlight(text, { language }).value;

  return (
    <div className={classes.codeContainer}>
      <pre className={cx(classes.code, classes.highlight)}>
        {parse(`${highlightedText}`)}
      </pre>
      <div className={classes.languageChip}>{language}</div>
      {commandToCopy && (
        <IconButton
          data-testid="Copy command"
          variant="ghost"
          className={classes.copyButton}
          icon={<ContentCopy fontSize="small" />}
          size="small"
          onClick={copyCommand}
        />
      )}
    </div>
  );
};

export default CopyCommand;
