/**
 * ShadCN-inspired Theme Design Tokens for React Native
 * Zinc / Slate modern minimalist color palette, clean borders, crisp typography
 */

export const COLORS = {
  // Primary (ShadCN zinc-900 / dark accent)
  primary: '#09090b',
  primaryForeground: '#fafafa',
  primaryLight: '#27272a',
  primaryDark: '#000000',

  // Secondary (zinc-100)
  secondary: '#f4f4f5',
  secondaryForeground: '#18181b',

  // Accent (blue / indigo subtle highlight)
  accent: '#2563eb',
  accentLight: '#eff6ff',
  accentDark: '#1d4ed8',

  // Background & Surface
  background: '#fafafa', // zinc-50
  cardBg: '#ffffff',     // pure card surface
  cardForeground: '#09090b',

  // Muted & Secondary Text
  muted: '#f4f4f5',
  mutedForeground: '#71717a', // zinc-500

  // Text Hierarchy
  textPrimary: '#09090b',     // zinc-950
  textSecondary: '#52525b',   // zinc-600
  textMuted: '#a1a1aa',       // zinc-400
  textLight: '#fafafa',

  // Borders & Inputs
  border: '#e4e4e7',          // zinc-200
  borderDark: '#27272a',
  inputBg: '#ffffff',
  inputBorder: '#e4e4e7',

  // Functional Status Colors (ShadCN style)
  success: '#10b981',
  successLight: '#ecfdf5',
  successBorder: '#a7f3d0',
  successForeground: '#047857',

  warning: '#f59e0b',
  warningLight: '#fffbeb',
  warningBorder: '#fde68a',
  warningForeground: '#b45309',

  danger: '#ef4444',
  dangerLight: '#fef2f2',
  dangerBorder: '#fecaca',
  dangerForeground: '#b91c1c',

  info: '#0284c7',
  infoLight: '#f0f9ff',
  infoBorder: '#bae6fd',
  infoForeground: '#0369a1',

  // Dark mode surfaces (if needed)
  darkBg: '#09090b',
  darkCard: '#18181b',
  darkBorder: '#27272a',
};

export const SPACING = {
  xs: 4,
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,
  xxl: 40,
};

export const FONTS = {
  tiny: 11,
  small: 12,
  body: 14,
  subtitle: 16,
  title: 18,
  header: 22,
  largeHeader: 26,
};

export const RADIUS = {
  sm: 6,
  md: 10,
  lg: 14,
  xl: 18,
  full: 9999,
};

export const SHADOWS = {
  none: {
    shadowColor: 'transparent',
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0,
    shadowRadius: 0,
    elevation: 0,
  },
  // ShadCN subtle hairline card shadow
  small: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.04,
    shadowRadius: 3,
    elevation: 1,
  },
  medium: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 8,
    elevation: 2,
  },
  large: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.08,
    shadowRadius: 16,
    elevation: 4,
  },
};
