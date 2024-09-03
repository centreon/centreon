import { equals } from 'ramda';

import { PaletteOptions, alpha } from '@mui/material';

import { ThemeMode } from '@centreon/ui-context';
import {
  colorBlue50,
  colorBlue400,
  colorBlue600,
  colorBlue900,
  colorGrey50,
  colorGrey100,
  colorGrey200,
  colorGrey300,
  colorGrey400,
  colorGrey500,
  colorGrey600,
  colorGrey700,
  colorGrey800,
  colorGrey900,
  colorGrey950
} from '../base/tokens/themes/base.tokens';

import {
  black,
  blue,
  blueGrey,
  green,
  grey,
  lightBlue,
  orange,
  pink,
  purple,
  red,
  sand,
  turquoise,
  white
} from './colors';

declare module '@mui/material/styles/createPalette' {
  interface TypeAction {
    acknowledged: string;
    acknowledgedBackground: string;
    inDowntime: string;
    inDowntimeBackground: string;
  }
}

declare module '@mui/material/styles' {
  interface Palette {
    background: TypeBackground;
    chip: TypeChip;
    header: TypeHeader;
    layout: TypeLayout;
    menu: TypeMenu;
    pending: {
      contrastText: string;
      main: string;
    };
    statusBackground: StatusBackground;
  }
  interface StatusBackground {
    error: string;
    none: string;
    pending: string;
    success: string;
    unknown: string;
    warning: string;
  }
  interface PaletteOptions {
    chip: TypeChip;
    header: TypeHeader;
    layout: TypeLayout;
    menu: TypeMenu;
    pending: {
      contrastText: string;
      main: string;
    };
    statusBackground: StatusBackground;
  }

  interface TypeBackground {
    default: string;
    listingHeader: string;
    panel: string;
    panelGroups: string;
    paper: string;
    tooltip: string;
    widget: string;
  }

  interface TypeLayout {
    body: {
      background: string;
    };
    header: {
      background: string;
      border: string;
    };
  }

  interface TypeHeader {
    page: {
      action: {
        background: {
          active: string;
          default: string;
        };
        color: {
          active: string;
          default: string;
        };
      };
      border: string;
      description: string;
      title: string;
    };
  }

  interface TypeMenu {
    background: string;
    button: {
      background: {
        active: string;
        default: string;
        hover: string;
      };
      color: {
        active: string;
        default: string;
        hover: string;
      };
    };
    divider: {
      border: string;
    };
    item: {
      background: {
        active: string;
        default: string;
        hover: string;
      };
      color: {
        active: string;
        default: string;
        hover: string;
      };
    };
  }

  interface TypeChip {
    color: {
      error: string;
      info: string;
      neutral: string;
      success: string;
      warning: string;
    };
  }
}

declare module '@mui/material/Button' {
  interface ButtonPropsColorOverrides {
    pending: true;
  }
}

declare module '@mui/material/Badge' {
  interface BadgePropsColorOverrides {
    pending: true;
  }
}

export const lightPalette: PaletteOptions = {
  action: {
    acknowledged: sand[700],
    acknowledgedBackground: sand[200],
    activatedOpacity: 0.12,
    active: grey[800],
    disabled: grey[700],
    disabledBackground: 'rgba(0, 0, 0, 0.12)',
    focus: 'rgba(0, 0, 0, 0.12)',
    focusOpacity: 0.12,
    hover: 'rgba(0, 0, 0, 0.06)',
    hoverOpacity: 0.06,
    inDowntime: purple[700],
    inDowntimeBackground: purple[200],
    selected: 'rgba(102, 102, 102, 0.3)',
    selectedOpacity: 0.3
  },
  background: {
    default: grey[200],
    listingHeader: grey[800],
    panel: grey[300],
    panelGroups: grey[200],
    paper: white[50],
    tooltip: blueGrey[700],
    widget: grey[200]
  },
  chip: {
    color: {
      error: red[500],
      info: lightBlue[500],
      neutral: grey[300],
      success: green[500],
      warning: orange[500]
    }
  },
  divider: grey[500],
  error: {
    contrastText: black[950],
    main: red[500]
  },
  header: {
    page: {
      action: {
        background: {
          active: alpha(lightBlue[500], 0.5),
          default: alpha(white[50], 0)
        },
        color: {
          active: lightBlue[500],
          default: grey[800]
        }
      },
      border: grey[800],
      description: grey[800],
      title: black[950]
    }
  },
  info: {
    contrastText: black[950],
    main: lightBlue[500]
  },
  layout: {
    body: {
      background: grey[200]
    },
    header: {
      background: white[50],
      border: grey[500]
    }
  },
  menu: {
    background: white[50],
    button: {
      background: {
        active: blue[100],
        default: 'transparent',
        hover: grey[200]
      },
      color: {
        active: blue[500],
        default: grey[500],
        hover: grey[600]
      }
    },
    divider: {
      border: grey[300]
    },
    item: {
      background: {
        active: blue[100],
        default: 'transparent',
        hover: grey[200]
      },
      color: {
        active: blue[500],
        default: grey[900],
        hover: grey[950]
      }
    }
  },
  mode: ThemeMode.light,
  pending: {
    contrastText: black[950],
    main: turquoise[500]
  },
  primary: {
    contrastText: white[50],
    dark: blue[800],
    light: blue[200],
    main: blue[500]
  },
  secondary: {
    contrastText: white[50],
    dark: pink[900],
    light: pink[400],
    main: pink[500]
  },
  statusBackground: {
    error: red[500],
    none: grey[500],
    pending: turquoise[500],
    success: green[500],
    unknown: grey[500],
    warning: orange[500]
  },
  success: {
    contrastText: black[950],
    main: green[500]
  },
  text: {
    disabled: grey[700],
    primary: black[950],
    secondary: grey[800]
  },
  warning: {
    contrastText: black[950],
    main: orange[500]
  }
};

export const darkPalette: PaletteOptions = {
  action: {
    acknowledged: sand[500],
    acknowledgedBackground: sand[900],
    activatedOpacity: 0.3,
    active: grey[600],
    disabled: grey[700],
    disabledBackground: grey[800],
    focus: 'rgba(255, 255, 255, 0.30)',
    focusOpacity: 0.3,
    hover: 'rgba(255, 255, 255, 0.16)',
    hoverOpacity: 0.16,
    inDowntime: purple[500],
    inDowntimeBackground: purple[900],
    selected: 'rgba(255, 255, 255, 0.5)',
    selectedOpacity: 0.5
  },
  background: {
    default: black[900],
    listingHeader: black[800],
    panel: black[800],
    panelGroups: grey[900],
    paper: grey[900],
    tooltip: '#AAB4C0',
    widget: black[800]
  },
  chip: {
    color: {
      error: red[800],
      info: lightBlue[600],
      neutral: grey[800],
      success: green[700],
      warning: orange[800]
    }
  },
  divider: black[800],
  error: {
    contrastText: white[50],
    main: red[800]
  },
  header: {
    page: {
      action: {
        background: {
          active: alpha(lightBlue[600], 0.5),
          default: white[50]
        },
        color: {
          active: lightBlue[600],
          default: grey[800]
        }
      },
      border: grey[600],
      description: grey[600],
      title: white[50]
    }
  },
  info: {
    contrastText: white[50],
    main: lightBlue[600]
  },
  layout: {
    body: {
      background: grey[200]
    },
    header: {
      background: white[50],
      border: grey[500]
    }
  },
  menu: {
    background: grey[950],
    button: {
      background: {
        active: grey[900],
        default: 'transparent',
        hover: grey[950]
      },
      color: {
        active: blue[700],
        default: grey[700],
        hover: grey[600]
      }
    },
    divider: {
      border: grey[900]
    },
    item: {
      background: {
        active: blue[900],
        default: 'transparent',
        hover: grey[900]
      },
      color: {
        active: blue[400],
        default: grey[50],
        hover: white[50]
      }
    }
  },
  mode: ThemeMode.dark,
  pending: {
    contrastText: white[50],
    main: turquoise[800]
  },
  primary: {
    contrastText: black[950],
    dark: blue[600],
    light: blue[200],
    main: blue[400]
  },
  secondary: {
    contrastText: white[50],
    dark: pink[950],
    light: pink[600],
    main: pink[800]
  },
  statusBackground: {
    error: red[800],
    none: grey[800],
    pending: turquoise[800],
    success: green[700],
    unknown: grey[800],
    warning: orange[800]
  },
  success: {
    contrastText: white[50],
    main: green[700]
  },
  text: {
    disabled: grey[800],
    primary: white[50],
    secondary: grey[600]
  },
  warning: {
    contrastText: white[50],
    main: orange[800]
  }
};

export const getPalette = (mode: ThemeMode): PaletteOptions =>
  equals(mode, ThemeMode.dark) ? darkPalette : lightPalette;
