import 'dart:developer';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:country_code_picker/country_code_picker.dart';
import 'package:dropdown_button2/dropdown_button2.dart';
import 'package:dropdown_search/dropdown_search.dart';
import 'package:flutter/material.dart';
import 'package:flutter_ecommerce/common/basewidget/custom_asset_image_widget.dart';
import 'package:flutter_ecommerce/features/address/domain/models/address_model.dart';
import 'package:flutter_ecommerce/features/auth/widgets/code_picker_widget.dart';
import 'package:flutter_ecommerce/features/checkout/controllers/checkout_controller.dart';
import 'package:flutter_ecommerce/features/location/controllers/location_controller.dart';
import 'package:flutter_ecommerce/features/location/screens/select_location_screen.dart';
import 'package:flutter_ecommerce/features/profile/controllers/profile_contrroller.dart';
import 'package:flutter_ecommerce/features/splash/domain/models/config_model.dart'
    as config;
import 'package:flutter_ecommerce/features/splash/domain/models/config_model.dart';
import 'package:flutter_ecommerce/helper/country_code_helper.dart';
import 'package:flutter_ecommerce/helper/velidate_check.dart';
import 'package:flutter_ecommerce/localization/controllers/localization_controller.dart';
import 'package:flutter_ecommerce/localization/language_constrants.dart';
import 'package:flutter_ecommerce/main.dart';
import 'package:flutter_ecommerce/features/auth/controllers/auth_controller.dart';
import 'package:flutter_ecommerce/features/address/controllers/address_controller.dart';
import 'package:flutter_ecommerce/features/splash/controllers/splash_controller.dart';
import 'package:flutter_ecommerce/theme/controllers/theme_controller.dart';
import 'package:flutter_ecommerce/utill/color_resources.dart';
import 'package:flutter_ecommerce/utill/custom_themes.dart';
import 'package:flutter_ecommerce/utill/dimensions.dart';
import 'package:flutter_ecommerce/utill/images.dart';
import 'package:flutter_ecommerce/common/basewidget/custom_button_widget.dart';
import 'package:flutter_ecommerce/common/basewidget/custom_app_bar_widget.dart';
import 'package:flutter_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';
import 'package:flutter_ecommerce/common/basewidget/success_dialog_widget.dart';
import 'package:flutter_ecommerce/common/basewidget/custom_textfield_widget.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:provider/provider.dart';

class AddNewAddressScreen extends StatefulWidget {
  final bool isEnableUpdate;
  final bool fromCheckout;
  final AddressModel? address;
  final bool? isBilling;
  const AddNewAddressScreen(
      {super.key,
      this.isEnableUpdate = false,
      this.address,
      this.fromCheckout = false,
      this.isBilling});

  @override
  State<AddNewAddressScreen> createState() => _AddNewAddressScreenState();
}

class _AddNewAddressScreenState extends State<AddNewAddressScreen> {
  final TextEditingController _contactPersonNameController =
      TextEditingController();
  final TextEditingController _contactPersonEmailController =
      TextEditingController();
  final TextEditingController _contactPersonNumberController =
      TextEditingController();
  final TextEditingController _cityController = TextEditingController();
  final TextEditingController _zipCodeController = TextEditingController();
  final TextEditingController _countryCodeController = TextEditingController();
  final FocusNode _addressNode = FocusNode();
  final FocusNode _nameNode = FocusNode();
  final FocusNode _emailNode = FocusNode();
  final FocusNode _numberNode = FocusNode();
  final FocusNode _cityNode = FocusNode();
  final FocusNode _zipNode = FocusNode();
  GoogleMapController? _controller;
  CameraPosition? _cameraPosition;
  bool _updateAddress = true;
  Address? _address;
  String zip = '', country = 'IN';
  late LatLng _defaut;

  String selectedCountryId = '2';

  final GlobalKey<FormState> _addressFormKey = GlobalKey();

  @override
  void initState() {
    super.initState();

    config.DefaultLocation? dLocation =
        Provider.of<SplashController>(context, listen: false)
            .configModel
            ?.defaultLocation;
    _defaut = LatLng(double.parse(dLocation?.lat ?? '0'),
        double.parse(dLocation?.lng ?? '0'));

    if (widget.isBilling!) {
      _address = Address.billing;
    } else {
      _address = Address.shipping;
    }

    Provider.of<AuthController>(context, listen: false).setCountryCode(
        CountryCode.fromCountryCode(
                Provider.of<SplashController>(context, listen: false)
                    .configModel!
                    .countryCode!)
            .dialCode!,
        notify: false);
    _countryCodeController.text = '2';
    Provider.of<AddressController>(context, listen: false).getAddressType();
    Provider.of<AddressController>(context, listen: false)
        .getRestrictedDeliveryCountryList();
    Provider.of<AddressController>(context, listen: false)
        .getRestrictedDeliveryZipList();

    _checkPermission(
        () => Provider.of<LocationController>(context, listen: false)
            .getCurrentLocation(context, true, mapController: _controller),
        context);
    if (widget.isEnableUpdate && widget.address != null) {
      _updateAddress = false;

      Provider.of<LocationController>(context, listen: false).updateMapPosition(
          CameraPosition(
              target: LatLng(
            (widget.address!.latitude != null &&
                    widget.address!.latitude != '0' &&
                    widget.address!.latitude != '')
                ? double.parse(widget.address!.latitude!)
                : _defaut.latitude,
            (widget.address!.longitude != null &&
                    widget.address!.longitude != '0' &&
                    widget.address!.longitude != '')
                ? double.parse(widget.address!.longitude!)
                : _defaut.longitude,
          )),
          true,
          widget.address!.address,
          context);

          _selectedArea=widget.address!.area;
          selectedCountryId=widget.address!.country!;
      _contactPersonNameController.text =
          '${widget.address?.contactPersonName}';
      _countryCodeController.text = '${widget.address?.country}';
      _contactPersonEmailController.text = '${widget.address?.email}';
      // _contactPersonNumberController.text = '${widget.address?.phone}';
      _cityController.text = '${widget.address?.city}';
      _zipCodeController.text = '${widget.address?.zip}';
      if (widget.address!.addressType == 'Home') {
        Provider.of<AddressController>(context, listen: false)
            .updateAddressIndex(0, false);
      } else if (widget.address!.addressType == 'Workplace') {
        Provider.of<AddressController>(context, listen: false)
            .updateAddressIndex(1, false);
      } else {
        Provider.of<AddressController>(context, listen: false)
            .updateAddressIndex(2, false);
      }
      String countryCode =
          CountryCodeHelper.getCountryCode(widget.address?.phone ?? '')!;
      Provider.of<AuthController>(context, listen: false)
          .setCountryCode(countryCode, notify: false);
      String phoneNumberOnly = CountryCodeHelper.extractPhoneNumber(
          countryCode, widget.address?.phone ?? '');
      _contactPersonNumberController.text = phoneNumberOnly;
    } else {
      if (Provider.of<ProfileController>(context, listen: false)
              .userInfoModel !=
          null) {
        _contactPersonNameController.text =
            '${Provider.of<ProfileController>(context, listen: false).userInfoModel!.fName ?? ''}'
            ' ${Provider.of<ProfileController>(context, listen: false).userInfoModel!.lName ?? ''}';

        String countryCode = CountryCodeHelper.getCountryCode(
            Provider.of<ProfileController>(context, listen: false)
                    .userInfoModel!
                    .phone ??
                '')!;
        Provider.of<AuthController>(context, listen: false)
            .setCountryCode(countryCode);
        String phoneNumberOnly = CountryCodeHelper.extractPhoneNumber(
            countryCode,
            Provider.of<ProfileController>(context, listen: false)
                    .userInfoModel!
                    .phone ??
                '');
        _contactPersonNumberController.text = phoneNumberOnly;
      }
    }
  }

  Area? _selectedArea;
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBar(
          title: widget.isEnableUpdate
              ? getTranslated('update_address', context)
              : getTranslated('add_new_address', context)),
      body: SingleChildScrollView(
        child: Column(
          children: [
            Consumer<AddressController>(
              builder: (context, addressController, child) {
                if (Provider.of<SplashController>(context, listen: false)
                            .configModel!
                            .deliveryCountryRestriction ==
                        1 &&
                    addressController.restrictedCountryList.isNotEmpty) {
                  _countryCodeController.text =
                      addressController.restrictedCountryList[0];
                }
                return Consumer<LocationController>(
                    builder: (context, locationController, _) {
                  return Padding(
                    padding: const EdgeInsets.symmetric(
                        horizontal: Dimensions.paddingSizeDefault),
                    child: Form(
                      key: _addressFormKey,
                      child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            //Country selection widget
                            const SizedBox(height: Dimensions.paddingSizeSmall),
                            Text(getTranslated('country', context)!,
                                style: textRegular.copyWith(
                                  color: Theme.of(context).hintColor,
                                  fontSize: Dimensions.fontSizeLarge,
                                )),

                            Padding(
                              padding: const EdgeInsets.only(top: 16.0),
                              child: SizedBox(
                                height: 50,
                                child: ListView.builder(
                                  itemCount: Provider.of<SplashController>(
                                          context,
                                          listen: false)
                                      .configModel!
                                      .countries!
                                      .length,
                                  scrollDirection: Axis.horizontal,
                                  itemBuilder:
                                      (BuildContext context, int index) {
                                    Country country =
                                        Provider.of<SplashController>(context,
                                                listen: false)
                                            .configModel!
                                            .countries![index];
                                    return Padding(
                                      padding: const EdgeInsets.all(2.0),
                                      child: Container(
                                        decoration: BoxDecoration(
                                            shape: BoxShape.circle,
                                            border:
                                                selectedCountryId == country.id
                                                    ? Border.all(
                                                        width: 1,
                                                        color: Theme.of(context)
                                                            .primaryColor)
                                                    : null),
                                        child: Padding(
                                          padding: const EdgeInsets.all(2.0),
                                          child: InkWell(
                                            splashColor: Colors.transparent,
                                            onTap: () {
                                              setState(() {
                                                selectedCountryId = country.id!;

                                                _countryCodeController.text=country.id!;
                                              });
                                            },
                                            child: ClipRRect(
                                              borderRadius:
                                                  BorderRadius.circular(22),
                                              child: CachedNetworkImage(
                                                height: 44,
                                                width: 44,
                                                imageUrl: country.flagUrl!,
                                                fit: BoxFit.fill,
                                              ),
                                            ),
                                            //Image.asset("assets/icons/$assets",height: 42,width: 42,)
                                          ),
                                        ),
                                      ),
                                    );
                                  },
                                ),
                              ),
                            ),

                            //Country  selection end

                            Padding(
                                padding: const EdgeInsets.only(
                                    top: Dimensions.paddingSizeLarge),
                                child: CustomTextFieldWidget(
                                  required: true,
                                  prefixIcon: Images.user,
                                  labelText: getTranslated(
                                      'enter_contact_person_name', context),
                                  hintText: getTranslated(
                                      'enter_contact_person_name', context),
                                  inputType: TextInputType.name,
                                  controller: _contactPersonNameController,
                                  focusNode: _nameNode,
                                  nextFocus: _numberNode,
                                  inputAction: TextInputAction.next,
                                  capitalization: TextCapitalization.words,
                                  validator: (value) =>
                                      ValidateCheck.validateEmptyText(value,
                                          'contact_person_name_is_required'),
                                )),

                            const SizedBox(
                                height: Dimensions.paddingSizeDefaultAddress),
                            Consumer<AuthController>(
                                builder: (context, authProvider, _) {
                              return CustomTextFieldWidget(
                                required: true,
                                labelText: getTranslated('phone', context),
                                hintText: getTranslated(
                                    'enter_mobile_number', context),
                                controller: _contactPersonNumberController,
                                focusNode: _numberNode,
                                nextFocus: _emailNode,
                                showCodePicker: true,
                                countryDialCode: authProvider.countryDialCode,
                                onCountryChanged: (CountryCode countryCode) {
                                  authProvider.countryDialCode =
                                      countryCode.dialCode!;
                                  authProvider
                                      .setCountryCode(countryCode.dialCode!);
                                },
                                isAmount: true,
                                validator: (value) =>
                                    ValidateCheck.validateEmptyText(
                                        value, "phone_must_be_required"),
                                inputAction: TextInputAction.next,
                                inputType: TextInputType.phone,
                              );
                            }),
                            const SizedBox(
                                height: Dimensions.paddingSizeDefaultAddress),

                            if (!Provider.of<AuthController>(context,
                                    listen: false)
                                .isLoggedIn())
                              CustomTextFieldWidget(
                                required: true,
                                prefixIcon: Images.email,
                                labelText: getTranslated('email', context),
                                hintText: getTranslated(
                                    'enter_contact_person_email', context),
                                inputType: TextInputType.emailAddress,
                                controller: _contactPersonEmailController,
                                focusNode: _emailNode,
                                nextFocus: _addressNode,
                                inputAction: TextInputAction.next,
                                capitalization: TextCapitalization.words,
                                validator: (value) =>
                                    ValidateCheck.validateEmail(value),
                              ),
                            const SizedBox(
                                height: Dimensions.paddingSizeDefaultAddress),

                            Provider.of<SplashController>(context,
                                            listen: false)
                                        .configModel!
                                        .mapApiStatus ==
                                    1
                                ? SizedBox(
                                    height:
                                        MediaQuery.of(context).size.width / 2,
                                    width: MediaQuery.of(context).size.width,
                                    child: ClipRRect(
                                        borderRadius: BorderRadius.circular(
                                            Dimensions.paddingSizeSmall),
                                        child: Stack(
                                            clipBehavior: Clip.none,
                                            children: [
                                              GoogleMap(
                                                  mapType: MapType.normal,
                                                  initialCameraPosition:
                                                      CameraPosition(
                                                          target: widget
                                                                  .isEnableUpdate
                                                              ? LatLng(
                                                                  (widget.address!.latitude != null &&
                                                                          widget.address!.latitude !=
                                                                              '0' &&
                                                                          widget.address!.latitude !=
                                                                              '')
                                                                      ? double.parse(widget
                                                                          .address!
                                                                          .latitude!)
                                                                      : _defaut
                                                                          .latitude,
                                                                  (widget.address!.longitude != null &&
                                                                          widget.address!.longitude !=
                                                                              '0' &&
                                                                          widget.address!.longitude !=
                                                                              '')
                                                                      ? double.parse(widget
                                                                          .address!
                                                                          .longitude!)
                                                                      : _defaut
                                                                          .longitude,
                                                                )
                                                              : LatLng(
                                                                  locationController
                                                                      .position
                                                                      .latitude,
                                                                  locationController
                                                                      .position
                                                                      .longitude),
                                                          zoom: 16),
                                                  onTap: (latLng) {
                                                    Navigator.of(context).push(
                                                        MaterialPageRoute(
                                                            builder: (BuildContext
                                                                    context) =>
                                                                SelectLocationScreen(
                                                                    googleMapController:
                                                                        _controller)));
                                                  },
                                                  zoomControlsEnabled: false,
                                                  compassEnabled: false,
                                                  indoorViewEnabled: true,
                                                  mapToolbarEnabled: false,
                                                  onCameraIdle: () {
                                                    if (_updateAddress) {
                                                      locationController
                                                          .updateMapPosition(
                                                              _cameraPosition,
                                                              true,
                                                              null,
                                                              context);
                                                    } else {
                                                      _updateAddress = true;
                                                    }
                                                  },
                                                  onCameraMove: ((position) =>
                                                      _cameraPosition =
                                                          position),
                                                  onMapCreated:
                                                      (GoogleMapController
                                                          controller) {
                                                    _controller = controller;
                                                    if (!widget
                                                            .isEnableUpdate &&
                                                        _controller != null) {
                                                      locationController
                                                          .getCurrentLocation(
                                                              context, true,
                                                              mapController:
                                                                  _controller);
                                                    }
                                                  }),
                                              locationController.loading
                                                  ? Center(
                                                      child: CircularProgressIndicator(
                                                          valueColor:
                                                              AlwaysStoppedAnimation<
                                                                  Color>(Theme.of(
                                                                      context)
                                                                  .primaryColor)))
                                                  : const SizedBox(),
                                              Container(
                                                  width: MediaQuery.of(context)
                                                      .size
                                                      .width,
                                                  alignment: Alignment.center,
                                                  height: MediaQuery.of(context)
                                                      .size
                                                      .height,
                                                  child: Icon(
                                                    Icons.location_on,
                                                    size: 40,
                                                    color: Theme.of(context)
                                                        .primaryColor,
                                                  )),
                                              Positioned(
                                                  top: 10,
                                                  right: 0,
                                                  child: InkWell(
                                                      onTap: () => Navigator.of(context)
                                                          .push(MaterialPageRoute(
                                                              builder: (BuildContext
                                                                      context) =>
                                                                  SelectLocationScreen(
                                                                      googleMapController:
                                                                          _controller))),
                                                      child: Container(
                                                          width: 30,
                                                          height: 30,
                                                          margin: const EdgeInsets.only(
                                                              right: Dimensions
                                                                  .paddingSizeLarge),
                                                          decoration:
                                                              BoxDecoration(
                                                            borderRadius:
                                                                BorderRadius.circular(
                                                                    Dimensions
                                                                        .paddingSizeSmall),
                                                            color: Colors.white,
                                                          ),
                                                          child: Icon(
                                                              Icons.fullscreen,
                                                              color: Theme.of(context)
                                                                  .primaryColor,
                                                              size: 20))))
                                            ])))
                                : const SizedBox(),

                            // Padding(
                            //   padding: const EdgeInsets.symmetric(
                            //       vertical: Dimensions.paddingSizeExtraSmall),
                            //   child: Text(getTranslated('label_us', context)!,
                            //       style: textRegular.copyWith(
                            //         color: ColorResources.getHint(context),
                            //         fontSize: Dimensions.fontSizeLarge,
                            //       )),
                            // ),

                            // SizedBox(
                            //     height: 50,
                            //     child: RepaintBoundary(
                            //       child: ListView.builder(
                            //           shrinkWrap: true,
                            //           scrollDirection: Axis.horizontal,
                            //           physics: const BouncingScrollPhysics(),
                            //           itemCount: addressController
                            //               .addressTypeList.length,
                            //           itemBuilder: (context, index) => InkWell(
                            //               onTap: () => addressController
                            //                   .updateAddressIndex(index, true),
                            //               child: Container(
                            //                   padding: const EdgeInsets.symmetric(
                            //                       vertical: Dimensions
                            //                           .paddingSizeDefault,
                            //                       horizontal: Dimensions
                            //                           .paddingSizeLarge),
                            //                   margin: const EdgeInsets.only(
                            //                       right: 17),
                            //                   decoration: BoxDecoration(
                            //                       borderRadius: BorderRadius.circular(
                            //                           Dimensions
                            //                               .paddingSizeSmall),
                            //                       border: Border.all(
                            //                           color: addressController.selectAddressIndex == index
                            //                               ? Theme.of(context).primaryColor
                            //                               : Theme.of(context).primaryColor.withValues(alpha: .125))),
                            //                   child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                            //                     SizedBox(
                            //                         width: 20,
                            //                         child: Image.asset(
                            //                             addressController
                            //                                 .addressTypeList[
                            //                                     index]
                            //                                 .icon,
                            //                             color:
                            //                                 addressController
                            //                                             .selectAddressIndex ==
                            //                                         index
                            //                                     ? Theme.of(
                            //                                             context)
                            //                                         .primaryColor
                            //                                     : Theme.of(
                            //                                             context)
                            //                                         .primaryColor
                            //                                         .withValues(
                            //                                             alpha:
                            //                                                 .35))),
                            //                     const SizedBox(
                            //                       width: Dimensions
                            //                           .paddingSizeSmall,
                            //                     ),
                            //                     Text(
                            //                         getTranslated(
                            //                             addressController
                            //                                 .addressTypeList[
                            //                                     index]
                            //                                 .title,
                            //                             context)!,
                            //                         style:
                            //                             textRegular.copyWith())
                            //                   ])))),
                            //     )),

                            // Padding(
                            //     padding: const EdgeInsets.only(
                            //         bottom: Dimensions.paddingSizeExtraSmall),
                            //     child: SizedBox(
                            //         height: 6,
                            //         child: Row(children: <Widget>[
                            //           // Row(
                            //           //   children: [
                            //           //     Radio<Address>(
                            //           //         value: Address.shipping,
                            //           //         groupValue: _address,
                            //           //         onChanged: (Address? value) {
                            //           //           setState(() {
                            //           //             _address = value;
                            //           //           });
                            //           //         }),
                            //           //     Text(
                            //           //       getTranslated('shipping_address',
                            //           //               context) ??
                            //           //           '',
                            //           //       style: textRegular.copyWith(
                            //           //           fontSize:
                            //           //               Dimensions.fontSizeLarge),
                            //           //     ),
                            //           //   ],
                            //           // ),
                            //           // Row(children: [
                            //           //   Radio<Address>(
                            //           //       value: Address.billing,
                            //           //       groupValue: _address,
                            //           //       onChanged: (Address? value) {
                            //           //         setState(() {
                            //           //           _address = value;
                            //           //         });
                            //           //       }),
                            //           //   Text(
                            //           //       getTranslated('billing_address',
                            //           //               context) ??
                            //           //           '',
                            //           //       style: textRegular.copyWith(
                            //           //           fontSize:
                            //           //               Dimensions.fontSizeLarge))
                            //           // ])
                            //         ]))),

                            if (selectedCountryId == '2') ...[
                              const SizedBox(
                                  height: Dimensions.paddingSizeExtraSmall),
                              SizedBox(
                                  height: 54,
                                  child: Consumer<AddressController>(
                                      builder: (context, addressController, _) {
                                    bool isArabic =
                                        Provider.of<LocalizationController>(
                                                    context)
                                                .locale
                                                .countryCode ==
                                            'SA';
                                    return Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          // area dropdown with search
                                          Container(
                                            width: MediaQuery.of(context)
                                                .size
                                                .width,
                                            decoration: BoxDecoration(
                                              color:
                                                  Theme.of(context).cardColor,
                                              borderRadius:
                                                  BorderRadius.circular(5),
                                              border: Border.all(
                                                  width: 0.1,
                                                  color: Theme.of(context)
                                                      .hintColor
                                                      .withOpacity(0.1)),
                                            ),
                                            child: DropdownSearch<Area>(
                                              selectedItem: _selectedArea,
                                              compareFn: (area1, area2) =>
                                                  isArabic
                                                      ? area1?.name_ar ==
                                                          area2?.name_ar
                                                      : area1?.name ==
                                                          area2?.name,

                                              items: (a, b) =>
                                                  Provider.of<SplashController>(
                                                          context,
                                                          listen: false)
                                                      .configModel!
                                                      .areas
                                                      .map((area) => area)
                                                      .toList(),
                                              itemAsString: (area) => isArabic
                                                  ? area!.name_ar!
                                                  : area!.name!,
                                             onChanged: (area){
                                              setState(() {
                                                _selectedArea=area;
                                              });
                                             },
                                              popupProps: PopupProps.menu(
                                                fit: FlexFit.loose,
                                                
                                                searchFieldProps:
                                                    TextFieldProps(
                                                        cursorColor:
                                                            Theme.of(context)
                                                                .primaryColor,
                                                        decoration:
                                                            InputDecoration(
                                                                prefixIcon:
                                                                    Padding(
                                                                  padding:
                                                                      const EdgeInsets
                                                                          .only(
                                                                          right:
                                                                              10,
                                                                          left:
                                                                              7),
                                                                  child: Icon(Icons
                                                                      .search),
                                                                ),
                                                                hintText: getTranslated(
                                                                    'area', context)!,
                                                                contentPadding:
                                                                    EdgeInsets.symmetric(
                                                                        horizontal:
                                                                            10),
                                                                enabledBorder: OutlineInputBorder(
                                                                    borderRadius:
                                                                        BorderRadius.circular(
                                                                            5),
                                                                    borderSide: BorderSide(
                                                                        width:
                                                                            1,
                                                                        color: Color(
                                                                            0xFFBFBFBF))),
                                                                focusedBorder: OutlineInputBorder(
                                                                    borderRadius:
                                                                        BorderRadius.circular(
                                                                            5),
                                                                    borderSide: BorderSide(
                                                                        width: 1,
                                                                        color: Color(0xFFBFBFBF))))),
                                                showSearchBox: true,
                                              ),
                                              decoratorProps:
                                                  DropDownDecoratorProps(
                                                decoration: InputDecoration(
                                                    contentPadding:
                                                        EdgeInsets.all(Dimensions
                                                            .fontSizeDefault),
                                                    alignLabelWithHint: true,
                                                    border: OutlineInputBorder(
                                                        borderRadius:
                                                            BorderRadius.circular(
                                                                8),
                                                        borderSide: BorderSide(
                                                          color: const Color(
                                                              0xFFBFBFBF),
                                                          width: .75,
                                                        )),
                                                    focusedBorder:
                                                        OutlineInputBorder(
                                                            borderRadius:
                                                                BorderRadius.circular(
                                                                    8),
                                                            borderSide:
                                                                BorderSide(
                                                              color: Theme.of(
                                                                      context)
                                                                  .primaryColor,
                                                              width: .75,
                                                            )),
                                                    enabledBorder:
                                                        OutlineInputBorder(
                                                            borderRadius:
                                                                BorderRadius.circular(
                                                                    8),
                                                            borderSide:
                                                                BorderSide(
                                                              color: const Color(
                                                                  0xFFBFBFBF),
                                                              width: .75,
                                                            )),
                                                    fillColor: Theme.of(context)
                                                        .cardColor,
                                                    filled: true,
                                                    labelStyle:
                                                        textRegular.copyWith(
                                                            fontSize: Dimensions
                                                                .fontSizeSmall,
                                                            color:
                                                                Theme.of(context)
                                                                    .hintColor),
                                                    label: Text.rich(
                                                        TextSpan(children: [
                                                      TextSpan(
                                                          text: getTranslated(
                                                              'area', context)!,
                                                          style: textRegular.copyWith(
                                                              fontSize: Dimensions
                                                                  .fontSizeDefault,
                                                              color: Theme.of(
                                                                      context)
                                                                  .textTheme
                                                                  .bodyLarge!
                                                                  .color)),
                                                      TextSpan(
                                                          text: ' *',
                                                          style: textRegular.copyWith(
                                                              color: Theme.of(
                                                                      context)
                                                                  .colorScheme
                                                                  .error,
                                                              fontSize: Dimensions
                                                                  .fontSizeLarge))
                                                    ])),
                                                    prefixIcon: Container(
                                                        padding: const EdgeInsets.all(1),
                                                        height: 24,
                                                        width: 24,
                                                        child: Center(
                                                          child: CustomAssetImageWidget(
                                                              height: 20,
                                                              width: 20,
                                                              Images.address,
                                                              color: Theme.of(
                                                                      context)
                                                                  .primaryColor
                                                                  .withValues(
                                                                      alpha:
                                                                          .4)),
                                                        ))),
                                              ),
                                            ),
                                          ),

                                          // Provider.of<SplashController>(context,
                                          //                 listen: false)
                                          //             .configModel!
                                          //             .deliveryCountryRestriction ==
                                          //         1
                                          //     ? Container(
                                          //         width: MediaQuery.of(context)
                                          //             .size
                                          //             .width,
                                          //         decoration: BoxDecoration(
                                          //             color: Theme.of(context)
                                          //                 .cardColor,
                                          //             borderRadius:
                                          //                 BorderRadius.circular(
                                          //                     5),
                                          //             border: Border.all(
                                          //                 width: .1,
                                          //                 color: Theme.of(
                                          //                         context)
                                          //                     .hintColor
                                          //                     .withValues(
                                          //                         alpha: 0.1))),
                                          //         child:
                                          //             DropdownButtonFormField2<
                                          //                 String>(
                                          //           isExpanded: true,
                                          //           isDense: true,
                                          //           decoration: InputDecoration(
                                          //               contentPadding:
                                          //                   const EdgeInsets
                                          //                       .symmetric(
                                          //                       vertical: 0),
                                          // border: OutlineInputBorder(
                                          //     borderRadius:
                                          //         BorderRadius
                                          //             .circular(
                                          //                 5))),
                                          //           hint: Row(
                                          //             children: [
                                          //               Image.asset(
                                          //                   Images.country),
                                          //               const SizedBox(
                                          //                   width: Dimensions
                                          //                       .paddingSizeSmall),
                                          //               Text(
                                          //                   _countryCodeController
                                          //                       .text,
                                          //                   style: textRegular.copyWith(
                                          //                       fontSize: Dimensions
                                          //                           .fontSizeDefault,
                                          //                       color: Theme.of(
                                          //                               context)
                                          //                           .textTheme
                                          //                           .bodyLarge!
                                          //                           .color)),
                                          //             ],
                                          //           ),
                                          //           items: addressController
                                          //               .restrictedCountryList
                                          //               .map((item) => DropdownMenuItem<
                                          //                       String>(
                                          //                   value: item,
                                          //                   child: Text(item,
                                          //                       style: textRegular
                                          //                           .copyWith(
                                          //                               fontSize:
                                          //                                   Dimensions.fontSizeSmall))))
                                          //               .toList(),
                                          //           onChanged: (value) {
                                          //             _countryCodeController
                                          //                 .text = value!;
                                          //           },
                                          //           buttonStyleData:
                                          //               const ButtonStyleData(
                                          //             padding: EdgeInsets.only(
                                          //                 right: 8),
                                          //           ),
                                          //           iconStyleData: IconStyleData(
                                          //               icon: Icon(
                                          //                   Icons
                                          //                       .arrow_drop_down,
                                          //                   color: Theme.of(
                                          //                           context)
                                          //                       .hintColor),
                                          //               iconSize: 24),
                                          //           dropdownStyleData:
                                          //               DropdownStyleData(
                                          //             decoration: BoxDecoration(
                                          //               borderRadius:
                                          //                   BorderRadius
                                          //                       .circular(5),
                                          //             ),
                                          //           ),
                                          //           menuItemStyleData:
                                          //               const MenuItemStyleData(
                                          //                   padding: EdgeInsets
                                          //                       .symmetric(
                                          //                           horizontal:
                                          //                               16)),
                                          //         ),
                                          //       )
                                          //     : Container(
                                          //         width: MediaQuery.of(context)
                                          //             .size
                                          //             .width,
                                          //         padding: const EdgeInsets
                                          //             .symmetric(
                                          //             vertical: Dimensions
                                          //                 .paddingSizeSmall),
                                          //         decoration: BoxDecoration(
                                          //             borderRadius: BorderRadius
                                          //                 .circular(Dimensions
                                          //                     .paddingSizeSmall),
                                          //             color: Theme.of(context)
                                          //                 .cardColor,
                                          //             border: Border.all(
                                          //                 color:
                                          //                     Theme.of(context)
                                          //                         .hintColor
                                          //                         .withValues(
                                          //                             alpha:
                                          //                                 .5))),
                                          //         child: CodePickerWidget(
                                          //           fromCountryList: true,
                                          //           padding: const EdgeInsets
                                          //               .only(
                                          //               left: Dimensions
                                          //                   .paddingSizeSmall),
                                          //           flagWidth: 25,
                                          //           onChanged: (val) {
                                          //             _countryCodeController
                                          //                 .text = val.name!;
                                          //             log("==ccc===>${val.flagUri}");
                                          //           },
                                          //           initialSelection:
                                          //               _countryCodeController
                                          //                   .text,
                                          //           showDropDownButton: true,
                                          //           showCountryOnly: false,
                                          //           showOnlyCountryWhenClosed:
                                          //               true,
                                          //           showFlagDialog: true,
                                          //           hideMainText: false,
                                          //           showFlagMain: false,
                                          //           dialogBackgroundColor:
                                          //               Theme.of(context)
                                          //                   .cardColor,
                                          //           barrierColor:
                                          //               Provider.of<ThemeController>(
                                          //                           context)
                                          //                       .darkTheme
                                          //                   ? Colors.black
                                          //                       .withValues(
                                          //                           alpha: 0.4)
                                          //                   : null,
                                          //           textStyle:
                                          //               textRegular.copyWith(
                                          //             fontSize: Dimensions
                                          //                 .fontSizeLarge,
                                          //             color: Theme.of(context)
                                          //                 .textTheme
                                          //                 .bodyLarge!
                                          //                 .color,
                                          //           ),
                                          //         ),
                                          //       ),
                                        ]);
                                  })),
                            ],
                            if (selectedCountryId == '2')
                              const SizedBox(height: 16),
                            CustomTextFieldWidget(
                              labelText: getTranslated(
                                  selectedCountryId == '2'
                                      ? 'city'
                                      : 'address_1',
                                  context),
                              hintText: getTranslated(
                                  selectedCountryId == '2'
                                      ? 'city'
                                      : 'address_1',
                                  context),
                              inputType: TextInputType.streetAddress,
                              inputAction: TextInputAction.next,
                              focusNode: _cityNode,
                              required: true,
                              nextFocus: _zipNode,
                              prefixIcon: Images.city,
                              controller: _cityController,
                              validator: (value) =>
                                  ValidateCheck.validateEmptyText(
                                      value,
                                      selectedCountryId == '2'
                                          ? 'city_is_required'
                                          : "address_1_required"),
                            ),
                            const SizedBox(
                                height: Dimensions.paddingSizeDefaultAddress),

                            CustomTextFieldWidget(
                              labelText: getTranslated(
                                  selectedCountryId == '2'
                                      ? 'street'
                                      : 'address_2',
                                  context),
                              hintText: getTranslated(
                                  selectedCountryId == '2'
                                      ? 'street'
                                      : 'address_2',
                                  context),
                              inputType: TextInputType.streetAddress,
                              inputAction: TextInputAction.next,
                              focusNode: _addressNode,
                              prefixIcon: Images.address,
                              required: true,
                              nextFocus: _cityNode,
                              controller: locationController.locationController,
                              validator: (value) =>
                                  ValidateCheck.validateEmptyText(
                                      value,
                                      selectedCountryId == '2'
                                          ? "address_is_required"
                                          : "address_2_required"),
                            ),
                            const SizedBox(
                                height: Dimensions.paddingSizeDefaultAddress),

                            Provider.of<SplashController>(context,
                                                listen: false)
                                            .configModel!
                                            .deliveryZipCodeAreaRestriction ==
                                        0 &&
                                    selectedCountryId == '2'
                                ? CustomTextFieldWidget(
                                    labelText: getTranslated('zip', context),
                                    hintText: getTranslated('zip', context),
                                    inputAction: TextInputAction.done,
                                    focusNode: _zipNode,
                                    required: selectedCountryId == '2',
                                    prefixIcon: Images.city,
                                    controller: _zipCodeController,
                                    validator: (value) =>
                                        ValidateCheck.validateEmptyText(
                                            value, 'zip_code_is_required'),
                                  )
                                : SizedBox(),
                            //     Container(
                            //         width: MediaQuery.of(context).size.width,
                            //         decoration: BoxDecoration(
                            //             color: Theme.of(context).cardColor,
                            //             borderRadius: BorderRadius.circular(5),
                            //             border: Border.all(
                            //                 width: .1,
                            //                 color: Theme.of(context)
                            //                     .hintColor
                            //                     .withValues(alpha: 0.1))),
                            //         child: DropdownButtonFormField2<String>(
                            //           isExpanded: true,
                            //           isDense: true,
                            //           decoration: InputDecoration(
                            //               contentPadding:
                            //                   const EdgeInsets.symmetric(
                            //                       vertical: 0),
                            //               border: OutlineInputBorder(
                            //                   borderRadius:
                            //                       BorderRadius.circular(5))),
                            //           hint: Row(
                            //             children: [
                            //               Image.asset(Images.city),
                            //               const SizedBox(
                            //                 width: Dimensions.paddingSizeSmall,
                            //               ),
                            //               Text(_zipCodeController.text,
                            //                   style: textRegular.copyWith(
                            //                       fontSize: Dimensions
                            //                           .fontSizeDefault,
                            //                       color: Theme.of(context)
                            //                           .textTheme
                            //                           .bodyLarge!
                            //                           .color)),
                            //             ],
                            //           ),
                            //           items: addressController.restrictedZipList
                            //               .map((item) => DropdownMenuItem<
                            //                       String>(
                            //                   value: item.zipcode,
                            //                   child: Text(item.zipcode!,
                            //                       style: textRegular.copyWith(
                            //                           fontSize: Dimensions
                            //                               .fontSizeSmall))))
                            //               .toList(),
                            //           onChanged: (value) {
                            //             _zipCodeController.text = value!;
                            //           },
                            //           buttonStyleData: const ButtonStyleData(
                            //             padding: EdgeInsets.only(right: 8),
                            //           ),
                            //           iconStyleData: IconStyleData(
                            //               icon: Icon(Icons.arrow_drop_down,
                            //                   color:
                            //                       Theme.of(context).hintColor),
                            //               iconSize: 24),
                            //           dropdownStyleData: DropdownStyleData(
                            //               decoration: BoxDecoration(
                            //                   borderRadius:
                            //                       BorderRadius.circular(5))),
                            //           menuItemStyleData:
                            //               const MenuItemStyleData(
                            //                   padding: EdgeInsets.symmetric(
                            //                       horizontal: 16)),
                            //         ),
                            //       ),
                            const SizedBox(
                                height: Dimensions.paddingSizeDefaultAddress),

                            Container(
                              height: 50.0,
                              margin: const EdgeInsets.all(
                                  Dimensions.paddingSizeSmall),
                              child: CustomButton(
                                isLoading: addressController.isLoading,
                                buttonText: widget.isEnableUpdate
                                    ? getTranslated('update_address', context)
                                    : getTranslated('save_location', context),
                                onTap: locationController.loading
                                    ? null
                                    : () {
                                        if (_addressFormKey.currentState
                                                ?.validate() ??
                                            false) {
                                          if (_selectedArea == null && selectedCountryId=='2') {
                                            showCustomSnackBar(
                                                '${getTranslated('area_is_required', context)}',
                                                context);
                                            return;
                                          }
                                          AddressModel addressModel =
                                              AddressModel(
                                            addressType: addressController
                                                .addressTypeList[
                                                    addressController
                                                        .selectAddressIndex]
                                                .title,
                                            contactPersonName:
                                                _contactPersonNameController
                                                    .text,
                                            phone:
                                                '${Provider.of<AuthController>(context, listen: false).countryDialCode}${_contactPersonNumberController.text.trim()}',
                                            email: _contactPersonEmailController
                                                .text
                                                .trim(),
                                            city: _cityController.text,
                                            zip: _zipCodeController.text,
                                            country:
                                                _countryCodeController.text,
                                            guestId:
                                                Provider.of<AuthController>(
                                                        context,
                                                        listen: false)
                                                    .getGuestToken(),
                                            isBilling:
                                                _address == Address.billing,
                                            address: locationController
                                                .locationController.text,
                                            latitude: widget.isEnableUpdate
                                                ? locationController
                                                    .position.latitude
                                                    .toString()
                                                : locationController
                                                    .position.latitude
                                                    .toString(),
                                            longitude: widget.isEnableUpdate
                                                ? locationController
                                                    .position.longitude
                                                    .toString()
                                                : locationController
                                                    .position.longitude
                                                    .toString(),
                                                    area: selectedCountryId=='2'? _selectedArea:null
                                          );

                                          print(
                                              "Addreesss is ${addressModel.toJson()}");
                                          if (widget.isEnableUpdate) {
                                            addressModel.id =
                                                widget.address!.id;
                                            addressController.updateAddress(
                                                context,
                                                addressModel: addressModel,
                                                addressId: addressModel.id);
                                          } 
                                          // else if (_countryCodeController.text
                                          //     .trim()
                                          //     .isEmpty) {
                                          //   showCustomSnackBar(
                                          //       '${getTranslated('country_is_required', context)}',
                                          //       context);
                                          // } 
                                          else {
                                            addressController
                                                .addAddress(addressModel)
                                                .then((value) {
                                              if (value.response?.statusCode ==
                                                  200) {
                                                Navigator.pop(context);
                                                if (widget.fromCheckout) {
                                                  Provider.of<CheckoutController>(
                                                          context,
                                                          listen: false)
                                                      .setAddressIndex(0);
                                                }
                                              }
                                            });
                                          }
                                        }
                                      },
                              ),
                            ),
                          ]),
                    ),
                  );
                });
              },
            ),
          ],
        ),
      ),
    );
  }

  void _checkPermission(Function callback, BuildContext context) async {
    LocationPermission permission = await Geolocator.requestPermission();
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.whileInUse) {
      InkWell(
          onTap: () async {
            Navigator.pop(context);
            await Geolocator.requestPermission();
            _checkPermission(callback, Get.context!);
          },
          child: AlertDialog(
              content: SuccessDialog(
                  icon: Icons.location_on_outlined,
                  title: '',
                  description: getTranslated('you_denied', Get.context!))));
    } else if (permission == LocationPermission.deniedForever) {
      InkWell(
          onTap: () async {
            if (context.mounted) {}
            Navigator.pop(context);
            await Geolocator.openAppSettings();
            _checkPermission(callback, Get.context!);
          },
          child: AlertDialog(
              content: SuccessDialog(
                  icon: Icons.location_on_outlined,
                  title: '',
                  description: getTranslated('you_denied', Get.context!))));
    } else {
      callback();
    }
  }
}

enum Address { shipping, billing }
