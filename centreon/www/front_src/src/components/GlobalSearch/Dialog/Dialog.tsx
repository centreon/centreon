import { TextField } from '@centreon/ui';
import ArrowForwardIcon from '@mui/icons-material/ArrowForward';
import ArticleIcon from '@mui/icons-material/Article';
import SearchIcon from '@mui/icons-material/Search';
import {
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  ListSubheader
} from '@mui/material';
import { equals, isEmpty } from 'ramda';
import { ChangeEvent, useEffect, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router';
import { useSearchPages } from '../hooks/useSearchPages';
import { useDialogStyles } from './Dialog.styles';

const Dialog = (): JSX.Element | null => {
  const { classes, cx } = useDialogStyles();
  const [isDisplayNone, setIsDisplayNone] = useState(true);
  const [isOpen, setIsOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [selectedOptionIndex, setSelectedIndex] = useState(0);
  const inputRef = useRef<null | HTMLDivElement>(null);
  const navigate = useNavigate();
  const { pathname } = useLocation();

  const pages = useSearchPages(search);

  const options = {
    Pages: pages.map((page) => ({ ...page, from: 'page' }))
  };

  const flattenedOptions = pages.map((page) => ({ ...page, from: 'page' }));

  const toggleDialog = (event: KeyboardEvent): void => {
    if (event.ctrlKey && equals(event.key, 'k')) {
      setIsDisplayNone(false);
      setTimeout(() => {
        setIsOpen(true);
      }, 50);
      setTimeout(() => {
        inputRef.current?.querySelector('input').focus();
      }, 300);
      return;
    }

    if (equals(event.key, 'Escape') && isOpen) {
      setIsOpen(false);
      setTimeout(() => {
        setIsDisplayNone(true);
      }, 350);

      setSearch('');
      inputRef.current?.blur();
      return;
    }
  };

  const inputKey = (event: KeyboardEvent): void => {
    const arrowDownKeyPressed = event.key === 'ArrowDown';
    const arrowUpKeyPressed = event.key === 'ArrowUp';
    const enterKeyPress = event.key === 'Enter';

    if (
      arrowDownKeyPressed &&
      selectedOptionIndex < flattenedOptions.length - 1
    ) {
      setSelectedIndex((current) => current + 1);
    }

    if (arrowUpKeyPressed && selectedOptionIndex > 0) {
      setSelectedIndex((current) => current - 1);
    }

    if (enterKeyPress) {
      if (flattenedOptions[selectedOptionIndex].from === 'page') {
        navigate(flattenedOptions[selectedOptionIndex].url);
        setIsOpen(false);
        setIsDisplayNone(true);
        setSearch('');
      }
    }
  };

  const pressOption = (idx: number) => (): void => {
    if (flattenedOptions[idx].from === 'page') {
      navigate(flattenedOptions[idx].url);
      setIsOpen(false);
      setIsDisplayNone(true);
      setSearch('');
    }
  };

  const changeSearch = (e: ChangeEvent<HTMLInputElement>): void => {
    setSearch(e.target.value);
    setSelectedIndex(0);
  };

  const loadIframe = () => {
    const iframe = document.getElementById(
      'main-content'
    ) as HTMLIFrameElement | null;
    iframe?.contentDocument.addEventListener('keydown', toggleDialog);
  };

  useEffect(() => {
    window.addEventListener('keydown', toggleDialog);

    const iframe = document.getElementById(
      'main-content'
    ) as HTMLIFrameElement | null;
    iframe?.addEventListener('load', loadIframe);

    return () => {
      window.removeEventListener('keydown', toggleDialog);

      iframe?.removeEventListener('load', loadIframe);
    };
  }, [isOpen, inputRef.current, isDisplayNone]);

  return (
    <div
      className={cx(
        classes.dialogWrapper,
        !isOpen && classes.dialogWrapperClosed,
        isDisplayNone && classes.displayWrapperNone
      )}
    >
      <div className={cx(classes.dialog, !isOpen && classes.dialogClosed)}>
        <TextField
          ref={inputRef}
          fullWidth
          dataTestId="global search"
          size="large"
          placeholder="What are you looking for?"
          StartAdornment={SearchIcon}
          value={search}
          onChange={changeSearch}
          onKeyDown={inputKey}
        />
        {search && (
          <List
            sx={{
              width: '100%',
              bgcolor: 'background.paper',
              position: 'relative',
              overflow: 'auto',
              maxHeight: '60%',
              '& ul': { padding: 0 }
            }}
            subheader={<li />}
          >
            {Object.entries(options).map(
              ([key, value], idx, array) =>
                !isEmpty(value) && (
                  <li key={key}>
                    <ul>
                      <ListSubheader
                        sx={{
                          bgcolor: 'background.listingHeader',
                          color: 'common.white'
                        }}
                      >
                        {key}
                      </ListSubheader>
                      {value.map(({ label, url }, valueIdx) => (
                        <ListItem
                          key={`page-${url}`}
                          data-selected={
                            valueIdx +
                              (idx === 0 ? 0 : array[idx - 1].value.length) ===
                            selectedOptionIndex
                          }
                          className={classes.listItem}
                          onClick={pressOption(
                            valueIdx +
                              (idx === 0 ? 0 : array[idx - 1].value.length)
                          )}
                          role="button"
                        >
                          <ListItemIcon>
                            <ArticleIcon />
                          </ListItemIcon>
                          <ListItemText primary={label} />
                          <ListItemIcon>
                            <ArrowForwardIcon />
                          </ListItemIcon>
                        </ListItem>
                      ))}
                    </ul>
                  </li>
                )
            )}
          </List>
        )}
      </div>
    </div>
  );
};

export default Dialog;
