import { TextField, useDebounce } from '@centreon/ui';
import ArrowForwardIcon from '@mui/icons-material/ArrowForward';
import ArticleIcon from '@mui/icons-material/Article';
import DvrIcon from '@mui/icons-material/Dvr';
import HistoryIcon from '@mui/icons-material/History';
import PanToolAltIcon from '@mui/icons-material/PanToolAlt';
import SearchIcon from '@mui/icons-material/Search';
import SettingsIcon from '@mui/icons-material/Settings';
import {
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  ListSubheader
} from '@mui/material';
import { useSetAtom } from 'jotai';
import { equals, isEmpty, pick, remove } from 'ramda';
import { ChangeEvent, useEffect, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router';
import {
  selectedResourceUuidAtom,
  selectedResourcesDetailsAtom,
  tabParametersAtom
} from 'www/front_src/src/Resources/Details/detailsAtoms';
import { useSearchActions } from '../hooks/useSearchActions';
import { useSearchPages } from '../hooks/useSearchPages';
import { useSearchResources } from '../hooks/useSearchResources';
import { useDialogStyles } from './Dialog.styles';

const Dialog = (): JSX.Element | null => {
  const { classes, cx } = useDialogStyles();
  const [isDisplayNone, setIsDisplayNone] = useState(true);
  const [isOpen, setIsOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [input, setInput] = useState('');
  const backdropRef = useRef<HTMLDivElement | null>(null);
  const listRef = useRef<HTMLDivElement | null>(null);

  const [globalSearchHistory, setGlobalSearchHistory] = useState([]);
  const [selectedOptionIndex, setSelectedIndex] = useState(0);
  const inputRef = useRef<null | HTMLDivElement>(null);
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const debounce = useDebounce({
    functionToDebounce: setSearch,
    wait: 500
  });

  const setSelectedResource = useSetAtom(selectedResourcesDetailsAtom);
  const setSelectedResourceUuid = useSetAtom(selectedResourceUuidAtom);
  const setTabParameters = useSetAtom(tabParametersAtom);

  const pages = useSearchPages(search);
  const resources = useSearchResources(search);
  const actions = useSearchActions(search);

  const formattedPages = pages.map((page) => ({ ...page, from: 'page' }));
  const formattedResources = resources.map((resource) => ({
    ...resource,
    from: 'resource'
  }));
  const formattedActions = actions.map((resource) => ({
    ...resource,
    from: 'action'
  }));

  const options = {
    Pages: formattedPages,
    Resources: formattedResources,
    Actions: formattedActions
  };

  const flattenedOptions = isEmpty(search)
    ? globalSearchHistory
    : [...formattedPages, ...formattedResources, ...formattedActions];

  const softClose = (): void => {
    setIsOpen(false);
    setTimeout(() => {
      setIsDisplayNone(true);
    }, 350);

    setSearch('');
    setInput('');
    setSelectedIndex(0);
    inputRef.current?.blur();
  };

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
      softClose();
      return;
    }

    if (isOpen) {
      inputKey(event);
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
      event.preventDefault();
      setSelectedIndex((current) => current + 1);
    }

    if (arrowUpKeyPressed && selectedOptionIndex > 0) {
      event.preventDefault();
      setSelectedIndex((current) => current - 1);
    }

    if (enterKeyPress) {
      event.preventDefault();
      const { resourceDetails, url, from, action } =
        flattenedOptions[selectedOptionIndex];
      if (['page', 'resource'].includes(from)) {
        if (
          pathname.startsWith('/monitoring/resources') &&
          url.startsWith('/monitoring/resources')
        ) {
          setSelectedResourceUuid(resourceDetails.uuid);
          setSelectedResource(
            pick(['resourceId', 'resourcesDetailsEndpoint'], resourceDetails)
          );
          setTabParameters({});
        }
        navigate(url);
      }

      if (equals(from, 'action')) {
        action();
      }

      softClose();
      setGlobalSearchHistory((current) => {
        if (!current.some((option) => equals(url, option.url))) {
          return [flattenedOptions[selectedOptionIndex], ...current];
        }

        const optionIndex = current.findIndex((option) =>
          equals(url, option.url)
        );

        const historyWithRemovedCurrent = remove(optionIndex, 1, current);
        return [
          flattenedOptions[selectedOptionIndex],
          ...historyWithRemovedCurrent
        ];
      });
    }
  };

  const pressOption = (idx: number) => (): void => {
    if (flattenedOptions[idx].from === 'page') {
      navigate(flattenedOptions[idx].url);
      softClose();
    }
  };

  const changeSearch = (e: ChangeEvent<HTMLInputElement>): void => {
    setInput(e.target.value);
    setSelectedIndex(0);
    debounce(e.target.value);
  };

  const loadIframe = () => {
    const iframe = document.getElementById(
      'main-content'
    ) as HTMLIFrameElement | null;
    iframe?.contentWindow.addEventListener('keydown', toggleDialog);
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
  }, [
    isOpen,
    inputRef.current,
    isDisplayNone,
    pathname,
    selectedOptionIndex,
    flattenedOptions,
    globalSearchHistory
  ]);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    const element = document.querySelector(
      'li[data-global-search-selected="true"]'
    );

    if (!element || !listRef.current) {
      return;
    }

    element.scrollIntoView({
      behavior: 'smooth',
      block: 'end',
      inline: 'end'
    });
  }, [isOpen, selectedOptionIndex]);

  return (
    <div
      className={cx(
        classes.dialogWrapper,
        !isOpen && classes.dialogWrapperClosed,
        isDisplayNone && classes.displayWrapperNone
      )}
      onClick={(e) => {
        if (equals(e.target, backdropRef.current)) {
          softClose();
        }
      }}
      ref={backdropRef}
    >
      <div className={cx(classes.dialog, !isOpen && classes.dialogClosed)}>
        <TextField
          ref={inputRef}
          fullWidth
          dataTestId="global search"
          size="large"
          placeholder="What are you looking for?"
          StartAdornment={SearchIcon}
          value={input}
          onChange={changeSearch}
        />
        {isEmpty(search) && !isEmpty(flattenedOptions) && (
          <List
            sx={{
              width: '100%',
              bgcolor: 'background.paper',
              position: 'relative',
              overflow: 'auto',
              maxHeight: '60vh',
              borderRadius: 1,
              '& ul': { padding: 0 }
            }}
          >
            {flattenedOptions.map(({ label, url }, idx) => (
              <ListItem
                key={`history-${url}`}
                data-global-earch-selected={idx === selectedOptionIndex}
                className={classes.listItem}
                onClick={pressOption(idx)}
                role="button"
              >
                <ListItemIcon>
                  <HistoryIcon />
                </ListItemIcon>
                <ListItemText primary={label} />
                <ListItemIcon>
                  <ArrowForwardIcon />
                </ListItemIcon>
              </ListItem>
            ))}
          </List>
        )}
        {search && (
          <List
            sx={{
              width: '100%',
              bgcolor: 'background.paper',
              position: 'relative',
              overflow: 'auto',
              maxHeight: '60vh',
              borderRadius: 1,
              '& ul': { padding: 0 }
            }}
            subheader={<li />}
            ref={listRef}
          >
            {Object.entries(options)
              .filter(([, value]) => !isEmpty(value))
              .map(([key, value]) => (
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
                    {value.map(
                      ({ label, url, from, type, resourceDetails }) => (
                        <ListItem
                          key={`page-${url}`}
                          data-global-search-selected={
                            flattenedOptions.findIndex((option) =>
                              equals(option.label, label)
                            ) === selectedOptionIndex
                          }
                          className={classes.listItem}
                          onClick={pressOption(
                            flattenedOptions.findIndex((option) =>
                              equals(option.label, label)
                            )
                          )}
                          role="button"
                        >
                          <ListItemIcon>
                            {equals(from, 'page') && <ArticleIcon />}
                            {equals(from, 'resource') &&
                              equals(type, 'configuration') && <SettingsIcon />}
                            {equals(from, 'resource') &&
                              equals(type, 'monitoring') && <DvrIcon />}
                            {equals(from, 'action') && <PanToolAltIcon />}
                          </ListItemIcon>
                          <ListItemText
                            primary={label}
                            secondary={
                              equals(from, 'resource')
                                ? resourceDetails?.parent?.name
                                : null
                            }
                          />
                          <ListItemIcon>
                            <ArrowForwardIcon />
                          </ListItemIcon>
                        </ListItem>
                      )
                    )}
                  </ul>
                </li>
              ))}
          </List>
        )}
      </div>
    </div>
  );
};

export default Dialog;
