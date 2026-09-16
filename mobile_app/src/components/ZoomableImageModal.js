import React, { useRef, useState, useEffect } from 'react';
import {
  Modal,
  View,
  Text,
  Image,
  TouchableOpacity,
  StyleSheet,
  Dimensions,
  Animated,
  PanResponder,
  StatusBar,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, FONTS } from '../constants/theme';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

export const ZoomableImageModal = ({ visible, imageUri, title, onClose }) => {
  const scale = useRef(new Animated.Value(1)).current;
  const translateX = useRef(new Animated.Value(0)).current;
  const translateY = useRef(new Animated.Value(0)).current;

  const currentScale = useRef(1);
  const currentTranslateX = useRef(0);
  const currentTranslateY = useRef(0);

  const [displayScale, setDisplayScale] = useState(1);
  const lastTap = useRef(0);
  const initialDistance = useRef(0);
  const initialScale = useRef(1);
  const initialTouchX = useRef(0);
  const initialTouchY = useRef(0);
  const initialPanX = useRef(0);
  const initialPanY = useRef(0);

  // Reset values when modal opens or image changes
  useEffect(() => {
    if (visible) {
      scale.setValue(1);
      translateX.setValue(0);
      translateY.setValue(0);
      currentScale.current = 1;
      currentTranslateX.current = 0;
      currentTranslateY.current = 0;
      setDisplayScale(1);
    }
  }, [visible, imageUri]);

  const resetZoom = () => {
    Animated.parallel([
      Animated.spring(scale, { toValue: 1, useNativeDriver: true }),
      Animated.spring(translateX, { toValue: 0, useNativeDriver: true }),
      Animated.spring(translateY, { toValue: 0, useNativeDriver: true }),
    ]).start(() => {
      currentScale.current = 1;
      currentTranslateX.current = 0;
      currentTranslateY.current = 0;
      setDisplayScale(1);
    });
  };

  const zoomTo = (targetScale) => {
    const clamped = Math.min(Math.max(targetScale, 1), 5);
    Animated.spring(scale, { toValue: clamped, useNativeDriver: true }).start(() => {
      currentScale.current = clamped;
      setDisplayScale(clamped);
      if (clamped === 1) {
        Animated.parallel([
          Animated.spring(translateX, { toValue: 0, useNativeDriver: true }),
          Animated.spring(translateY, { toValue: 0, useNativeDriver: true }),
        ]).start(() => {
          currentTranslateX.current = 0;
          currentTranslateY.current = 0;
        });
      }
    });
  };

  const panResponder = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onMoveShouldSetPanResponder: () => true,

      onPanResponderGrant: (evt) => {
        const touches = evt.nativeEvent.touches;
        if (touches.length === 2) {
          const t1 = touches[0];
          const t2 = touches[1];
          initialDistance.current = Math.hypot(t1.pageX - t2.pageX, t1.pageY - t2.pageY);
          initialScale.current = currentScale.current;
        } else if (touches.length === 1) {
          // Double-tap detection
          const now = Date.now();
          if (now - lastTap.current < 300) {
            if (currentScale.current > 1.2) {
              resetZoom();
            } else {
              zoomTo(2.5);
            }
          }
          lastTap.current = now;

          initialTouchX.current = evt.nativeEvent.pageX;
          initialTouchY.current = evt.nativeEvent.pageY;
          initialPanX.current = currentTranslateX.current;
          initialPanY.current = currentTranslateY.current;
        }
      },

      onPanResponderMove: (evt) => {
        const touches = evt.nativeEvent.touches;
        if (touches.length === 2) {
          // Pinch-to-zoom
          const t1 = touches[0];
          const t2 = touches[1];
          const currentDist = Math.hypot(t1.pageX - t2.pageX, t1.pageY - t2.pageY);
          if (initialDistance.current > 0) {
            const factor = currentDist / initialDistance.current;
            const newScale = Math.min(Math.max(initialScale.current * factor, 1), 5);
            scale.setValue(newScale);
            currentScale.current = newScale;
            setDisplayScale(Math.round(newScale * 10) / 10);
          }
        } else if (touches.length === 1 && currentScale.current > 1) {
          // Pan/drag when zoomed
          const dx = evt.nativeEvent.pageX - initialTouchX.current;
          const dy = evt.nativeEvent.pageY - initialTouchY.current;
          const maxPanX = (SCREEN_WIDTH * (currentScale.current - 1)) / 1.8;
          const maxPanY = (SCREEN_HEIGHT * (currentScale.current - 1)) / 1.8;

          const nextX = Math.min(Math.max(initialPanX.current + dx, -maxPanX), maxPanX);
          const nextY = Math.min(Math.max(initialPanY.current + dy, -maxPanY), maxPanY);

          translateX.setValue(nextX);
          translateY.setValue(nextY);
          currentTranslateX.current = nextX;
          currentTranslateY.current = nextY;
        }
      },

      onPanResponderRelease: (evt) => {
        if (currentScale.current < 1) {
          resetZoom();
        }
      },
    })
  ).current;

  if (!visible || !imageUri) return null;

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <StatusBar barStyle="light-content" backgroundColor="#000" />
      <View style={styles.container}>
        {/* Top Header Bar */}
        <View style={styles.headerBar}>
          <View style={{ flex: 1 }}>
            {title ? <Text style={styles.titleText} numberOfLines={1}>{title}</Text> : null}
            <Text style={styles.hintText}>Pinch with 2 fingers to zoom • Double-tap to toggle 2.5x</Text>
          </View>
          <TouchableOpacity style={styles.closeBtn} onPress={onClose} activeOpacity={0.7}>
            <Ionicons name="close" size={24} color="#fff" />
          </TouchableOpacity>
        </View>

        {/* Zoomable Image Container */}
        <View style={styles.imageWrapper} {...panResponder.panHandlers}>
          <Animated.Image
            source={{ uri: imageUri }}
            style={[
              styles.image,
              {
                transform: [
                  { scale: scale },
                  { translateX: translateX },
                  { translateY: translateY },
                ],
              },
            ]}
            resizeMode="contain"
          />
        </View>

        {/* Bottom Zoom Controls Bar */}
        <View style={styles.bottomControls}>
          <TouchableOpacity
            style={styles.zoomControlBtn}
            onPress={() => zoomTo(currentScale.current - 0.5)}
            activeOpacity={0.7}
          >
            <Ionicons name="remove" size={20} color="#fff" />
          </TouchableOpacity>

          <TouchableOpacity style={styles.zoomPill} onPress={resetZoom} activeOpacity={0.7}>
            <Text style={styles.zoomPillText}>{Math.round(displayScale * 100)}%</Text>
            {displayScale > 1 && (
              <Text style={styles.resetText}> (Reset)</Text>
            )}
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.zoomControlBtn}
            onPress={() => zoomTo(currentScale.current + 0.5)}
            activeOpacity={0.7}
          >
            <Ionicons name="add" size={20} color="#fff" />
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#000',
  },
  headerBar: {
    position: 'absolute',
    top: 40,
    left: 16,
    right: 16,
    zIndex: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: 'rgba(20, 20, 20, 0.75)',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 12,
  },
  titleText: {
    color: '#fff',
    fontSize: 15,
    fontWeight: '700',
  },
  hintText: {
    color: 'rgba(255, 255, 255, 0.65)',
    fontSize: 11,
    marginTop: 2,
  },
  closeBtn: {
    padding: 6,
    borderRadius: 20,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    marginLeft: 10,
  },
  imageWrapper: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    overflow: 'hidden',
  },
  image: {
    width: SCREEN_WIDTH,
    height: SCREEN_HEIGHT * 0.75,
  },
  bottomControls: {
    position: 'absolute',
    bottom: 36,
    alignSelf: 'center',
    zIndex: 20,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(30, 30, 30, 0.85)',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 30,
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.15)',
    gap: 8,
  },
  zoomControlBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: 'rgba(255, 255, 255, 0.15)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  zoomPill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  zoomPillText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 14,
  },
  resetText: {
    color: COLORS.primaryLight || '#90caf9',
    fontSize: 12,
    fontWeight: '600',
  },
});
