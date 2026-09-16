import React from 'react';
import { TouchableOpacity, Text, StyleSheet, ActivityIndicator, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, FONTS, RADIUS } from '../constants/theme';

export const CustomButton = ({
  title,
  onPress,
  variant = 'default', // 'default', 'secondary', 'outline', 'destructive', 'ghost', 'success'
  size = 'default',    // 'default', 'sm', 'lg', 'icon'
  disabled = false,
  loading = false,
  icon,
  iconPosition = 'left',
  iconColor,
  style,
  textStyle,
}) => {
  // Determine Button Variant Styles
  const getVariantStyles = () => {
    switch (variant) {
      case 'secondary':
        return {
          button: styles.btnSecondary,
          text: styles.textSecondary,
          iconDefaultColor: COLORS.secondaryForeground,
        };
      case 'outline':
        return {
          button: styles.btnOutline,
          text: styles.textOutline,
          iconDefaultColor: COLORS.textPrimary,
        };
      case 'destructive':
        return {
          button: styles.btnDestructive,
          text: styles.textDestructive,
          iconDefaultColor: '#ffffff',
        };
      case 'ghost':
        return {
          button: styles.btnGhost,
          text: styles.textGhost,
          iconDefaultColor: COLORS.textPrimary,
        };
      case 'success':
        return {
          button: styles.btnSuccess,
          text: styles.textSuccess,
          iconDefaultColor: '#ffffff',
        };
      case 'default':
      default:
        return {
          button: styles.btnDefault,
          text: styles.textDefault,
          iconDefaultColor: COLORS.primaryForeground,
        };
    }
  };

  // Determine Size Styles
  const getSizeStyles = () => {
    switch (size) {
      case 'sm':
        return {
          paddingVertical: 7,
          paddingHorizontal: 12,
          fontSize: 12,
          iconSize: 14,
        };
      case 'lg':
        return {
          paddingVertical: 14,
          paddingHorizontal: 22,
          fontSize: 15,
          iconSize: 18,
        };
      case 'icon':
        return {
          paddingVertical: 8,
          paddingHorizontal: 8,
          width: 36,
          height: 36,
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: 13,
          iconSize: 18,
        };
      case 'default':
      default:
        return {
          paddingVertical: 10,
          paddingHorizontal: 16,
          fontSize: 13,
          iconSize: 16,
        };
    }
  };

  const vStyles = getVariantStyles();
  const sStyles = getSizeStyles();

  return (
    <TouchableOpacity
      onPress={onPress}
      disabled={disabled || loading}
      activeOpacity={0.8}
      style={[
        styles.baseButton,
        vStyles.button,
        { paddingVertical: sStyles.paddingVertical, paddingHorizontal: sStyles.paddingHorizontal },
        disabled && styles.btnDisabled,
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator
          size="small"
          color={variant === 'outline' || variant === 'secondary' || variant === 'ghost' ? COLORS.primary : '#ffffff'}
        />
      ) : (
        <View style={styles.contentRow}>
          {icon && iconPosition === 'left' && (
            <Ionicons
              name={icon}
              size={sStyles.iconSize}
              color={iconColor || vStyles.iconDefaultColor}
              style={{ marginRight: size === 'icon' ? 0 : 6 }}
            />
          )}

          {title ? (
            <Text
              style={[
                styles.baseText,
                vStyles.text,
                { fontSize: sStyles.fontSize },
                disabled && styles.textDisabled,
                textStyle,
              ]}
            >
              {title}
            </Text>
          ) : null}

          {icon && iconPosition === 'right' && (
            <Ionicons
              name={icon}
              size={sStyles.iconSize}
              color={iconColor || vStyles.iconDefaultColor}
              style={{ marginLeft: 6 }}
            />
          )}
        </View>
      )}
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  baseButton: {
    borderRadius: RADIUS.md,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
  },
  contentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
  },
  baseText: {
    fontWeight: '600',
    letterSpacing: -0.2,
  },

  // Variants
  btnDefault: {
    backgroundColor: COLORS.primary,
  },
  textDefault: {
    color: COLORS.primaryForeground,
  },

  btnSecondary: {
    backgroundColor: COLORS.secondary,
    borderWidth: 1,
    borderColor: '#e4e4e7',
  },
  textSecondary: {
    color: COLORS.secondaryForeground,
  },

  btnOutline: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  textOutline: {
    color: COLORS.textPrimary,
  },

  btnDestructive: {
    backgroundColor: COLORS.danger,
  },
  textDestructive: {
    color: '#ffffff',
  },

  btnSuccess: {
    backgroundColor: COLORS.success,
  },
  textSuccess: {
    color: '#ffffff',
  },

  btnGhost: {
    backgroundColor: 'transparent',
  },
  textGhost: {
    color: COLORS.textPrimary,
  },

  btnDisabled: {
    opacity: 0.5,
  },
  textDisabled: {
    color: COLORS.textMuted,
  },
});
