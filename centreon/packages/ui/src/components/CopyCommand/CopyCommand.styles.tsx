import { ThemeMode } from '@centreon/ui-context';
import { Theme } from '@mui/material';
import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

const isDarkMode = (theme: Theme): boolean => {
  return equals(theme.palette.mode, ThemeMode.dark);
};

export const useCopyCommandStyle = makeStyles()((theme) => ({
  code: {
    background: theme.palette.background.default,
    padding: theme.spacing(1.5),
    borderRadius: theme.shape.borderRadius
  },
  codeContainer: {
    position: 'relative'
  },
  languageChip: {
    position: 'absolute',
    top: 0,
    right: 0,
    borderRadius: `0px ${theme.shape.borderRadius}px ${theme.shape.borderRadius}px ${theme.shape.borderRadius}px`,
    background: theme.palette.background.listingHeader,
    color: theme.palette.common.white,
    padding: `0px ${theme.spacing(0.5)}`,
    fontSize: theme.typography.body2.fontSize
  },
  copyButton: {
    position: 'absolute',
    top: theme.spacing(0.5),
    right: theme.spacing(6),
    opacity: 0.8,
    '&:hover': {
      opacity: 1
    }
  },
  highlight: {
    '.hljs': {
      color: isDarkMode(theme) ? '#c9d1d9' : '#24292e'
    },
    '.hljs-doctag, .hljs-keyword, .hljs-meta, .hljs-keyword, .hljs-template-tag, .hljs-template-variable, .hljs-type, .hljs-variable.language_':
      {
        color: isDarkMode(theme) ? '#ff7b72' : '#d73a49'
      },
    '.hljs-title, .hljs-title.class,_ .hljs-title.class_.inherited,__ .hljs-title.function_':
      {
        color: isDarkMode(theme) ? '#d2a8ff' : '#6f42c1'
      },
    '.hljs-attr, .hljs-attribute, .hljs-literal, .hljs-meta, .hljs-number, .hljs-operator, .hljs-variable, .hljs-selector-attr, .hljs-selector-class, .hljs-selector-id':
      {
        color: isDarkMode(theme) ? '#79c0ff' : '#005cc5'
      },
    '.hljs-regexp, .hljs-string, .hljs-meta, .hljs-string': {
      color: isDarkMode(theme) ? '#a5d6ff' : '#032f62'
    },
    '.hljs-built_in, .hljs-symbol': {
      color: isDarkMode(theme) ? '#ffa657' : '#e36209'
    },
    '.hljs-code, .hljs-comment, .hljs-formula': {
      color: isDarkMode(theme) ? '#8b949e' : '#6a737d'
    },
    '.hljs-name, .hljs-quote, .hljs-selector-tag, .hljs-selector-pseudo': {
      color: isDarkMode(theme) ? '#7ee787' : '#22863a'
    },
    '.hljs-subst': {
      color: isDarkMode(theme) ? '#c9d1d9' : '#24292e'
    },
    '.hljs-section': {
      color: isDarkMode(theme) ? '#1f6feb' : '#005cc5',
      fontWeight: theme.typography.fontWeightBold
    },
    '.hljs-bullet': {
      color: isDarkMode(theme) ? '#f2cc60' : '#735c0f'
    },
    '.hljs-emphasis': {
      color: isDarkMode(theme) ? '#c9d1d9' : '#24292e',
      fontStyle: 'italic'
    },
    '.hljs-strong': {
      color: isDarkMode(theme) ? '#c9d1d9' : '#24292e',
      fontWeight: theme.typography.fontWeightBold
    },
    '.hljs-addition': {
      color: isDarkMode(theme) ? '#aff5b4' : '#22863a'
    },
    '.hljs-deletion': {
      color: isDarkMode(theme) ? '#ffdcd7' : '#b31d28'
    },
    '.hljs-char.escape_, .hljs-link, .hljs-params, .hljs-property, .hljs-punctuation, .hljs-tag':
      {}
  }
}));
