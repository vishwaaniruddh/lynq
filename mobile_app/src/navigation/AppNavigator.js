import React from 'react';
import { View, ActivityIndicator } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../context/AuthContext';
import { LoginScreen } from '../screens/LoginScreen';
import { DashboardScreen } from '../screens/DashboardScreen';
import { SitesListScreen } from '../screens/SitesListScreen';
import { FeasibilityListScreen } from '../screens/FeasibilityListScreen';
import { InstallationScreen } from '../screens/InstallationScreen';
import { InventoryScreen } from '../screens/InventoryScreen';
import { SiteDetailScreen } from '../screens/SiteDetailScreen';
import { FeasibilityScreen } from '../screens/FeasibilityScreen';
import { ProfileScreen } from '../screens/ProfileScreen';
import { COLORS } from '../constants/theme';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

// 5 Main Tabs for Engineer: Dashboard, Sites, Feasibility, Installation, Inventory
const MainTabs = () => {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarActiveTintColor: COLORS.textPrimary,
        tabBarInactiveTintColor: COLORS.mutedForeground,
        tabBarStyle: {
          backgroundColor: '#ffffff',
          borderTopColor: COLORS.border,
          borderTopWidth: 1,
          height: 62,
          paddingBottom: 8,
          paddingTop: 6,
        },
        tabBarLabelStyle: {
          fontSize: 10,
          fontWeight: '600',
          letterSpacing: -0.2,
        },
        tabBarIcon: ({ focused, color, size }) => {
          let iconName;

          if (route.name === 'DashboardTab') {
            iconName = focused ? 'grid' : 'grid-outline';
          } else if (route.name === 'SitesTab') {
            iconName = focused ? 'business' : 'business-outline';
          } else if (route.name === 'FeasibilityTab') {
            iconName = focused ? 'clipboard' : 'clipboard-outline';
          } else if (route.name === 'InstallationTab') {
            iconName = focused ? 'construct' : 'construct-outline';
          } else if (route.name === 'InventoryTab') {
            iconName = focused ? 'cube' : 'cube-outline';
          }

          return <Ionicons name={iconName} size={20} color={color} />;
        },
      })}
    >
      <Tab.Screen
        name="DashboardTab"
        component={DashboardScreen}
        options={{ tabBarLabel: 'Dashboard' }}
      />
      <Tab.Screen
        name="SitesTab"
        component={SitesListScreen}
        options={{ tabBarLabel: 'Sites' }}
      />
      <Tab.Screen
        name="FeasibilityTab"
        component={FeasibilityListScreen}
        options={{ tabBarLabel: 'Feasibility' }}
      />
      <Tab.Screen
        name="InstallationTab"
        component={InstallationScreen}
        options={{ tabBarLabel: 'Installation' }}
      />
      <Tab.Screen
        name="InventoryTab"
        component={InventoryScreen}
        options={{ tabBarLabel: 'Inventory' }}
      />
    </Tab.Navigator>
  );
};

export const AppNavigator = () => {
  const { isAuthenticated, isBootstrapping } = useAuth();

  if (isBootstrapping) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: COLORS.background }}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  return (
    <NavigationContainer>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {!isAuthenticated ? (
          <Stack.Screen name="Login" component={LoginScreen} />
        ) : (
          <>
            <Stack.Screen name="Main" component={MainTabs} />
            <Stack.Screen name="SiteDetail" component={SiteDetailScreen} />
            <Stack.Screen name="Feasibility" component={FeasibilityScreen} />
            <Stack.Screen name="ProfileTab" component={ProfileScreen} />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
};
