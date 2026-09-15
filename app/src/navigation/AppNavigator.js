import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { Ionicons } from '@expo/vector-icons';
import { colors } from '../theme/colors';
import { useAuth } from '../context/AuthContext';

// Screens
import { LoginScreen } from '../screens/auth/LoginScreen';
import { DashboardScreen } from '../screens/dashboard/DashboardScreen';
import { AssignedSitesScreen } from '../screens/sites/AssignedSitesScreen';
import { SiteDetailScreen } from '../screens/sites/SiteDetailScreen';
import { FeasibilityListScreen } from '../screens/feasibility/FeasibilityListScreen';
import { FeasibilityFormScreen } from '../screens/feasibility/FeasibilityFormScreen';
import { InstallationListScreen } from '../screens/installation/InstallationListScreen';
import { InstallationUpdateScreen } from '../screens/installation/InstallationUpdateScreen';
import { PendingReceivesScreen } from '../screens/materials/PendingReceivesScreen';
import { ProfileScreen } from '../screens/profile/ProfileScreen';

const Tab = createBottomTabNavigator();
const Stack = createNativeStackNavigator();

const MainTabs = () => {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.textMuted,
        tabBarStyle: {
          backgroundColor: colors.card,
          borderTopColor: colors.borderLight,
          height: 60,
          paddingBottom: 8,
          paddingTop: 6,
        },
        tabBarLabelStyle: {
          fontSize: 10,
          fontWeight: '700',
        },
        tabBarIcon: ({ focused, color, size }) => {
          let iconName;

          if (route.name === 'DashboardTab') {
            iconName = focused ? 'home' : 'home-outline';
          } else if (route.name === 'SitesTab') {
            iconName = focused ? 'location' : 'location-outline';
          } else if (route.name === 'FeasibilityTab') {
            iconName = focused ? 'clipboard' : 'clipboard-outline';
          } else if (route.name === 'InstallationsTab') {
            iconName = focused ? 'construct' : 'construct-outline';
          } else if (route.name === 'ProfileTab') {
            iconName = focused ? 'person' : 'person-outline';
          }

          return <Ionicons name={iconName} size={20} color={color} />;
        },
      })}
    >
      <Tab.Screen name="DashboardTab" component={DashboardScreen} options={{ tabBarLabel: 'Dashboard' }} />
      <Tab.Screen name="SitesTab" component={AssignedSitesScreen} options={{ tabBarLabel: 'My Sites' }} />
      <Tab.Screen name="FeasibilityTab" component={FeasibilityListScreen} options={{ tabBarLabel: 'Feasibility' }} />
      <Tab.Screen name="InstallationsTab" component={InstallationListScreen} options={{ tabBarLabel: 'Installations' }} />
      <Tab.Screen name="ProfileTab" component={ProfileScreen} options={{ tabBarLabel: 'Profile' }} />
    </Tab.Navigator>
  );
};

export const AppNavigator = () => {
  const { isLoggedIn, isLoading } = useAuth();

  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      {!isLoggedIn ? (
        <Stack.Screen name="Login" component={LoginScreen} />
      ) : (
        <>
          <Stack.Screen name="Main" component={MainTabs} />
          <Stack.Screen name="Sites" component={AssignedSitesScreen} />
          <Stack.Screen name="SiteDetail" component={SiteDetailScreen} />
          <Stack.Screen name="FeasibilityForm" component={FeasibilityFormScreen} />
          <Stack.Screen name="InstallationUpdate" component={InstallationUpdateScreen} />
          <Stack.Screen name="PendingReceives" component={PendingReceivesScreen} />
          <Stack.Screen name="Profile" component={ProfileScreen} />
        </>
      )}
    </Stack.Navigator>
  );
};
