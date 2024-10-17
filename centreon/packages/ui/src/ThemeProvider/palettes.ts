import { equals } from 'ramda';

import {
  type PaletteOptions as PaletteOptionsModel,
  alpha
} from '@mui/material';

import { ThemeMode } from '@centreon/ui-context';

import * as BaseTokens from '../base/tokens/themes/base.tokens';

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

export const lightPalette: PaletteOptionsModel = {
  action: {
    acknowledged: '#745F35',
    acknowledgedBackground: '#DFD2B9',
    activatedOpacity: 0.12,
    active: '#666666',
    disabled: '#999999',
    disabledBackground: 'rgba(0, 0, 0, 0.12)',
    focus: 'rgba(0, 0, 0, 0.12)',
    focusOpacity: 0.12,
    hover: 'rgba(0, 0, 0, 0.06)',
    hoverOpacity: 0.06,
    inDowntime: '#512980',
    inDowntimeBackground: '#E5D8F3',
    selected: 'rgba(102, 102, 102, 0.3)',
    selectedOpacity: 0.3
  },
  background: {
    default: '#F4F4F4',
    listingHeader: '#666666',
    panel: '#EDEDED',
    panelGroups: '#F5F5F5',
    paper: '#FFFFFF',
    tooltip: '#434E5B',
    widget: '#F8F8F8'
  },
  chip: {
    color: {
      error: '#FF6666',
      info: '#1588D1',
      neutral: BaseTokens.colorGrey300,
      success: '#88B922',
      warning: '#FD9B27'
    }
  },
  divider: '#E3E3E3',
  error: {
    contrastText: '#000',
    main: '#FF4A4A'
  },
  header: {
    page: {
      action: {
        background: {
          active: '#1975D10F',
          default: '#FFFFFF00'
        },
        color: {
          active: '#1976D2',
          default: '#696969'
        }
      },
      border: '#4A4A4A',
      description: '#4A4A4A',
      title: '#000000'
    }
  },
  info: {
    contrastText: '#000',
    main: '#1588D1'
  },
  layout: {
    body: {
      background: '#F6F6F6'
    },
    header: {
      background: '#FFFFFF',
      border: '#E3E3E3'
    }
  },
  menu: {
    background: '#FFFFFF',
    button: {
      background: {
        active: BaseTokens.colorBlue50,
        default: 'transparent',
        hover: BaseTokens.colorGrey100
      },
      color: {
        active: BaseTokens.colorBlue400,
        default: BaseTokens.colorGrey400,
        hover: BaseTokens.colorGrey500
      }
    },
    divider: {
      border: BaseTokens.colorGrey200
    },
    item: {
      background: {
        active: BaseTokens.colorBlue50,
        default: 'transparent',
        hover: BaseTokens.colorGrey100
      },
      color: {
        active: BaseTokens.colorBlue400,
        default: BaseTokens.colorGrey900,
        hover: BaseTokens.colorGrey950
      }
    }
  },
  mode: ThemeMode.light,
  pending: {
    contrastText: '#000',
    main: '#1EBEB3'
  },
  primary: {
    contrastText: '#fff',
    dark: '#255891',
    light: '#cde7fc',
    main: '#2E68AA'
  },
  secondary: {
    contrastText: '#fff',
    dark: '#ac28c1',
    light: '#e5a5f0',
    main: '#C772D6'
  },
  statusBackground: {
    error: '#FF6666',
    none: alpha('#2E68AA', 0.1),
    pending: '#1EBEB3',
    success: '#88B922',
    unknown: '#E3E3E3',
    warning: '#FD9B27'
  },
  success: {
    contrastText: '#000',
    main: '#88B922'
  },
  text: {
    disabled: '#999999',
    primary: '#000000',
    secondary: '#666666'
  },
  warning: {
    contrastText: '#000',
    main: '#FD9B27'
  }
};

export const darkPalette: PaletteOptionsModel = {
  action: {
    acknowledged: '#DFD2B9',
    acknowledgedBackground: '#745F35',
    activatedOpacity: 0.3,
    active: '#B5B5B5',
    disabled: '#999999',
    disabledBackground: '#555555',
    focus: 'rgba(255, 255, 255, 0.30)',
    focusOpacity: 0.3,
    hover: 'rgba(255, 255, 255, 0.16)',
    hoverOpacity: 0.16,
    inDowntime: '#E5D8F3',
    inDowntimeBackground: '#512980',
    selected: 'rgba(255, 255, 255, 0.5)',
    selectedOpacity: 0.5
  },
  background: {
    default: '#4a4a4a',
    listingHeader: '#666666',
    panel: '#4a4a4a',
    panelGroups: '#252525',
    paper: '#212121',
    tooltip: '#AAB4C0',
    widget: '#2E2E2E'
  },
  chip: {
    color: {
      error: '#D60101',
      info: '#1CA9F4',
      neutral: BaseTokens.colorGrey700,
      success: '#5F8118',
      warning: '#C55400'
    }
  },
  divider: '#666666',
  error: {
    contrastText: '#fff',
    main: '#D60101'
  },
  header: {
    page: {
      action: {
        background: {
          active: '#1975D10F',
          default: '#FFFFFF00'
        },
        color: {
          active: '#1976D2',
          default: '#696969'
        }
      },
      border: '#bdbdbd',
      description: '#bdbdbd',
      title: '#fff'
    }
  },
  info: {
    contrastText: '#fff',
    main: '#1CA9F4'
  },
  layout: {
    body: {
      background: '#F6F6F6'
    },
    header: {
      background: '#FFFFFF',
      border: '#E3E3E3'
    }
  },
  menu: {
    background: BaseTokens.colorGrey950,
    button: {
      background: {
        active: BaseTokens.colorBlue900,
        default: 'transparent',
        hover: BaseTokens.colorGrey900
      },
      color: {
        active: BaseTokens.colorBlue600,
        default: BaseTokens.colorGrey600,
        hover: BaseTokens.colorGrey500
      }
    },
    divider: {
      border: BaseTokens.colorGrey800
    },
    item: {
      background: {
        active: BaseTokens.colorBlue900,
        default: 'transparent',
        hover: BaseTokens.colorGrey900
      },
      color: {
        active: BaseTokens.colorBlue400,
        default: BaseTokens.colorGrey50,
        hover: '#fff'
      }
    }
  },
  mode: ThemeMode.dark,
  pending: {
    contrastText: '#fff',
    main: '#118077'
  },
  primary: {
    contrastText: '#000',
    dark: '#4974A5',
    light: '#8bbff9',
    main: '#6eaff8'
  },
  secondary: {
    contrastText: '#fff',
    main: '#7C1FA2'
  },
  statusBackground: {
    error: '#D60101',
    none: alpha('#2E68AA', 0.1),
    pending: '#118077',
    success: '#5F8118',
    unknown: '#666666',
    warning: '#C55400'
  },
  success: {
    contrastText: '#fff',
    main: '#5F8118'
  },
  text: {
    disabled: '#666666',
    primary: '#FFFFFF',
    secondary: '#CCCCCC'
  },
  warning: {
    contrastText: '#fff',
    main: '#C55400'
  }
};

export const getPalette = (mode: ThemeMode): PaletteOptionsModel =>
  equals(mode, ThemeMode.dark) ? darkPalette : lightPalette;
